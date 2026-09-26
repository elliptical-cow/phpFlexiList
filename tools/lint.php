<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
$failed = false;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
    exec($command, $output, $status);
    if ($status !== 0) {
        $failed = true;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
    $output = [];
}

if ($failed) {
    exit(1);
}
fwrite(STDOUT, "PHP syntax check passed.\n");

