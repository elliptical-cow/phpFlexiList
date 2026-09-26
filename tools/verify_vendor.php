<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$expected = [
    'public/vendor/vue.global.prod.js' => '4963101441ded7e420c05665e7c616b2f2e3851c99e1cf8af84d29d6f10e77da',
    'public/vendor/fast-json-patch.min.js' => '648f0ec7fa90867a0ba4727c849dd70d2aeb77c34f498cda10795c28d2bc6771',
];

foreach ($expected as $relativePath => $hash) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path) || !hash_equals($hash, hash_file('sha256', $path))) {
        fwrite(STDERR, "Vendor integrity check failed: {$relativePath}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Vendor integrity check passed.\n");

