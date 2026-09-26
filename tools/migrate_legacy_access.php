<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use FlexiList\Models\ChecklistModel;
use FlexiList\Security\ListAccessService;
use FlexiList\Services\ChecklistService;

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tools/migrate_legacy_access.php DATA_DIR MAPPING_FILE\n");
    exit(2);
}

$dataDirectory = rtrim($argv[1], DIRECTORY_SEPARATOR);
$mappingFile = $argv[2];

if (!is_dir($dataDirectory)) {
    fwrite(STDERR, "Data directory does not exist: {$dataDirectory}\n");
    exit(2);
}
if (file_exists($mappingFile)) {
    fwrite(STDERR, "Refusing to replace mapping file: {$mappingFile}\n");
    exit(2);
}

$mappingHandle = fopen($mappingFile, 'x');
if ($mappingHandle === false) {
    fwrite(STDERR, "Unable to create mapping file: {$mappingFile}\n");
    exit(2);
}
chmod($mappingFile, 0600);

$access = new ListAccessService($dataDirectory);
$checklists = new ChecklistService($dataDirectory);
$mappings = [];
$failed = false;

foreach (glob($dataDirectory . '/*.json') ?: [] as $legacyPath) {
    $legacyId = pathinfo($legacyPath, PATHINFO_FILENAME);

    // A list with access metadata is already on the secure format.
    if (preg_match('/^[a-f0-9]{24}$/', $legacyId)
        && is_file($dataDirectory . '/.access/' . $legacyId . '.json')
    ) {
        continue;
    }

    try {
        $decoded = json_decode((string) file_get_contents($legacyPath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('List root is not an object');
        }
        $model = new ChecklistModel($decoded);
        $credentials = $access->issue();

        try {
            $checklists->createChecklist($credentials['id']);
            $checklists->updateChecklist($credentials['id'], $model);
        } catch (Throwable $error) {
            $access->revoke($credentials['id']);
            $newPath = $dataDirectory . '/' . $credentials['id'] . '.json';
            if (is_file($newPath)) {
                unlink($newPath);
            }
            throw $error;
        }

        $mappings[$legacyId] = [
            'id' => $credentials['id'],
            'token' => $credentials['token'],
            'path' => '/app?id=' . $credentials['id'] . '#token=' . $credentials['token'],
        ];
        fwrite(STDOUT, "Migrated {$legacyId}\n");
    } catch (Throwable $error) {
        $failed = true;
        fwrite(STDERR, "Failed {$legacyId}: {$error->getMessage()}\n");
    }
}

fwrite($mappingHandle, json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
fwrite($mappingHandle, PHP_EOL);
fclose($mappingHandle);

fwrite(STDOUT, "Created protected mapping file: {$mappingFile}\n");
fwrite(STDOUT, "Legacy files were not modified or deleted. Store the mapping like a password.\n");

exit($failed ? 1 : 0);

