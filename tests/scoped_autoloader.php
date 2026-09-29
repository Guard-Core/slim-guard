<?php

/**
 * Scoped PSR-4 autoloader for bin/test_slim_no_psr7.php.
 *
 * That suite covers the "slim/psr7 is not installed" deployment shape of
 * SlimGuard::forApp(), which composer's autoloader cannot reach: slim/psr7 is
 * a dev dependency here, so composer's classmap resolves the soft
 * `class_exists(Slim\Psr7\Factory\StreamFactory)` reference in src/SlimGuard.php
 * and the no-package fallback branch is dead code in a composer-booted
 * process. This autoloader maps exactly the packages the suite needs (the
 * engine, psr15-guard, slim/slim, PSR interfaces) and deliberately does NOT
 * map slim/psr7, reproducing the production shape in which the soft reference
 * cannot resolve.
 *
 * NOTE: never require composer's autoloader from a script that uses this one.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    // psr/http-factory declares factory interfaces in the Psr\Http\Message
    // namespace, so a namespace prefix alone does not identify the package:
    // every candidate location is probed and the first hit wins.
    $prefixes = [
        'RenzoFranceschini\\GuardCorePsr15\\' => __DIR__ . '/../vendor/rennf93/psr15-guard/src/',
        'RenzoFranceschini\\GuardCore\\' => __DIR__ . '/../vendor/rennf93/guard-core-php/src/',
        'RenzoFranceschini\\GuardCoreSlim\\' => __DIR__ . '/../src/',
        'Psr\\Http\\Message\\' => [
            __DIR__ . '/../vendor/psr/http-factory/src/',
            __DIR__ . '/../vendor/psr/http-message/src/',
        ],
        'Psr\\Http\\Server\\' => [
            __DIR__ . '/../vendor/psr/http-server-handler/src/',
            __DIR__ . '/../vendor/psr/http-server-middleware/src/',
        ],
        'Psr\\Container\\' => __DIR__ . '/../vendor/psr/container/src/',
        'Psr\\Log\\' => __DIR__ . '/../vendor/psr/log/src/',
        'FastRoute\\' => __DIR__ . '/../vendor/nikic/fast-route/src/',
        'Slim\\' => __DIR__ . '/../vendor/slim/slim/Slim/',
    ];
    foreach ($prefixes as $prefix => $baseDirs) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        foreach ((array) $baseDirs as $baseDir) {
            $file = $baseDir . $relative;
            if (is_file($file)) {
                require $file;

                return;
            }
        }
    }
});
