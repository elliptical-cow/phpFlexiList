<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use FlexiList\Config\ConfigLoader;
use FlexiList\Models\ChecklistModel;
use FlexiList\Models\ValidationException;
use FlexiList\Security\AccessDeniedException;
use FlexiList\Security\ListAccessService;
use FlexiList\Security\RateLimiter;
use FlexiList\Services\ChecklistService;
use FlexiList\Services\TemplateRenderer;
use FlexiList\Utils\JsonPatch;

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[$name] = $callback;
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertThrows = static function (string $class, callable $callback) use ($assert): void {
    try {
        $callback();
    } catch (Throwable $error) {
        $assert($error instanceof $class, 'Expected ' . $class . ', got ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected exception ' . $class);
};

$temporaryRoot = sys_get_temp_dir() . '/flexilist-tests-' . bin2hex(random_bytes(8));
mkdir($temporaryRoot, 0700, true);

$test('access tokens are random, hashed and required', function () use ($temporaryRoot, $assert, $assertThrows): void {
    $data = $temporaryRoot . '/access-data';
    $access = new ListAccessService($data);
    $first = $access->issue();
    $second = $access->issue();

    $assert((bool) preg_match('/^[a-f0-9]{24}$/', $first['id']));
    $assert(strlen($first['token']) >= 40);
    $assert($first !== $second);
    $metadata = file_get_contents($data . '/.access/' . $first['id'] . '.json');
    $assert(!str_contains((string) $metadata, $first['token']), 'Plain token was persisted');
    $access->assertAuthorized($first['id'], 'Bearer ' . $first['token']);
    $assertThrows(AccessDeniedException::class, fn() => $access->assertAuthorized($first['id'], 'Bearer wrong'));
});

$test('checklists require explicit creation and persist atomically', function () use ($temporaryRoot, $assert, $assertThrows): void {
    $data = $temporaryRoot . '/lists';
    $service = new ChecklistService($data);
    $id = str_repeat('a', 24);

    $assertThrows(ValidationException::class, fn() => $service->getChecklist($id));
    $service->createChecklist($id);
    $model = new ChecklistModel([
        'Metadata' => ['Title' => 'Test', 'Hide_Checked' => false],
        'Checklist' => [[
            'Type' => 'Item',
            'Name' => 'Milk',
            'Order' => 1,
            'Checked' => false,
            'Notes' => '',
        ]],
    ]);
    $service->updateChecklist($id, $model);
    $stored = $service->getChecklist($id);
    $assert($stored['Metadata']['Title'] === 'Test');
    $assert($stored['Checklist'][0]['Name'] === 'Milk');
    $assert(glob($data . '/*.tmp.*') === [], 'Temporary files were left behind');
});

$test('checklist validation enforces payload limits', function () use ($assertThrows): void {
    $assertThrows(ValidationException::class, fn() => new ChecklistModel([
        'Checklist' => [[
            'Type' => 'Item',
            'Name' => str_repeat('x', 501),
            'Order' => 1,
        ]],
    ]));
});

$test('rate limiter rejects requests after the configured limit', function () use ($temporaryRoot, $assert): void {
    $limiter = new RateLimiter($temporaryRoot . '/limits');
    $assert($limiter->consume('client', 2, 60));
    $assert($limiter->consume('client', 2, 60));
    $assert(!$limiter->consume('client', 2, 60));
});

$test('site templates override defaults and receive safe tokens', function () use ($temporaryRoot, $assert): void {
    $defaults = $temporaryRoot . '/templates-default';
    $overrides = $temporaryRoot . '/templates-site';
    mkdir($defaults, 0700, true);
    mkdir($overrides, 0700, true);
    file_put_contents($defaults . '/landing.html', 'Default {{ appName }}');
    file_put_contents($overrides . '/landing.de.html', 'Site {{ appName }}');
    $renderer = new TemplateRenderer($defaults, $overrides, ['{{ appName }}' => 'Example']);
    $assert($renderer->render('landing', 'de-DE') === 'Site Example');
    $assert($renderer->render('landing', 'en-US') === 'Default Example');
});

$test('FTP secrets configuration overrides site configuration', function () use ($temporaryRoot, $assert): void {
    $root = $temporaryRoot . '/config-layering';
    mkdir($root . '/config', 0700, true);
    copy(dirname(__DIR__) . '/config/defaults.php', $root . '/config/defaults.php');
    file_put_contents(
        $root . '/config/site.php',
        "<?php\nreturn ['app' => ['name' => 'Site'], 'openrouter' => ['api_key' => null]];\n"
    );
    file_put_contents(
        $root . '/config/secrets.php',
        "<?php\nreturn ['openrouter' => ['api_key' => 'local-secret']];\n"
    );

    $config = ConfigLoader::load($root);
    $assert($config['app']['name'] === 'Site');
    $assert($config['openrouter']['api_key'] === 'local-secret');
});

$test('legacy migration creates protected copies without changing originals', function () use ($temporaryRoot, $assert): void {
    $data = $temporaryRoot . '/legacy';
    mkdir($data, 0700, true);
    $legacyPath = $data . '/shopping.json';
    $legacy = [
        'Metadata' => ['Title' => 'Shopping', 'Hide_Checked' => false],
        'Checklist' => [],
    ];
    file_put_contents($legacyPath, json_encode($legacy));
    $mappingPath = $temporaryRoot . '/mapping.json';
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/migrate_legacy_access.php')
        . ' ' . escapeshellarg($data)
        . ' ' . escapeshellarg($mappingPath)
        . ' 2>&1';
    exec($command, $output, $status);

    $assert($status === 0, implode("\n", $output));
    $assert(is_file($legacyPath), 'Legacy list was removed');
    $mapping = json_decode((string) file_get_contents($mappingPath), true);
    $assert(isset($mapping['shopping']['id'], $mapping['shopping']['token']));
    $assert(is_file($data . '/' . $mapping['shopping']['id'] . '.json'));
    $access = new ListAccessService($data);
    $access->assertAuthorized(
        $mapping['shopping']['id'],
        'Bearer ' . $mapping['shopping']['token']
    );
});

$test('JSON Patch applies a validated replacement', function () use ($assert): void {
    $updated = JsonPatch::apply(
        ['Metadata' => ['Title' => 'Old']],
        [['op' => 'replace', 'path' => '/Metadata/Title', 'value' => 'New']]
    );
    $assert($updated['Metadata']['Title'] === 'New');
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n");
    }
}

$removeTree = static function (string $directory) use (&$removeTree): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            $removeTree($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};
$removeTree($temporaryRoot);

exit($failures === 0 ? 0 : 1);
