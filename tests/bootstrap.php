<?php

declare(strict_types=1);

/**
 * Test bootstrap.
 *
 * This plugin has no vendor/ of its own yet, so the PSR-4 map is registered by
 * hand here. Once `composer install` has run in the plugin, vendor/autoload.php
 * supersedes this and phpunit.xml can point at it instead.
 */
spl_autoload_register(static function (string $class): void {
    foreach ([
        'Plugins\\SchoolClasses\\Tests\\' => __DIR__ . '/',
        'Plugins\\SchoolClasses\\'        => dirname(__DIR__) . '/',
    ] as $prefix => $base) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }
        $rel  = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
