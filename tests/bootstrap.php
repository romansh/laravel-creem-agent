<?php

require dirname(__DIR__).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Romansh\\LaravelCreem\\' => '/home/roman/php/run/laravel-creem/src/',
        'Romansh\\LaravelCreemCli\\' => '/home/roman/php/run/laravel-creem-cli/src/',
    ];

    foreach ($prefixes as $prefix => $basePath) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        $filePath = $basePath.$relativePath;

        if (is_file($filePath)) {
            require_once $filePath;
        }

        return;
    }
});