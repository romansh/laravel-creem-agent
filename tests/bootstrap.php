<?php

require dirname(__DIR__).'/vendor/autoload.php';

$phpunitCachePath = '/tmp/laravel-creem-agent-phpunit-cache';
if (! is_dir($phpunitCachePath)) {
    @mkdir($phpunitCachePath, 0777, true);
}

@chmod($phpunitCachePath, 0777);

$defaultSkeleton = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel';
$writableSkeleton = '/tmp/laravel-creem-agent-testbench-skeleton';

if (! function_exists('creem_agent_copy_dir')) {
    function creem_agent_copy_dir(string $source, string $destination): void
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $items = scandir($source) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source.DIRECTORY_SEPARATOR.$item;
            $to = $destination.DIRECTORY_SEPARATOR.$item;

            if (is_dir($from)) {
                creem_agent_copy_dir($from, $to);
                continue;
            }

            copy($from, $to);
        }
    }
}

if (is_dir($defaultSkeleton) && ! is_dir($writableSkeleton)) {
    creem_agent_copy_dir($defaultSkeleton, $writableSkeleton);
}

$testbenchBootstrapCache = $writableSkeleton.'/bootstrap/cache';
if (! is_dir($testbenchBootstrapCache)) {
    @mkdir($testbenchBootstrapCache, 0777, true);
}

@chmod($writableSkeleton, 0777);
@chmod($writableSkeleton.'/bootstrap', 0777);
@chmod($testbenchBootstrapCache, 0777);

putenv('APP_BASE_PATH='.$writableSkeleton);
$_ENV['APP_BASE_PATH'] = $writableSkeleton;
$_SERVER['APP_BASE_PATH'] = $writableSkeleton;

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
