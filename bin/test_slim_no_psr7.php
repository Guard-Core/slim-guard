<?php

/**
 * SlimGuard::forApp() under the "slim/psr7 is not installed" deployment shape.
 *
 * composer's autoloader cannot cover the soft-reference fallback in
 * src/SlimGuard.php: slim/psr7 is a dev dependency of this package, so in any
 * composer-booted process class_exists(Slim\Psr7\Factory\StreamFactory) is
 * trivially true and the no-package LogicException branch is unreachable. This
 * suite boots through tests/scoped_autoloader.php (no composer autoloader, no
 * slim/psr7 mapping), which reproduces the production shape the branch exists
 * for: an App whose PSR-7 stack is not slim/psr7 and no explicit stream
 * factory, which must fail with the documented guidance instead of a fatal.
 *
 * Run via the coverage runner's collect mode:
 *   php .github/coverage-runner.php --collect bin/test_slim_no_psr7.php <out>
 */

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RenzoFranceschini\GuardCore\Config\SecurityConfig;
use RenzoFranceschini\GuardCore\Engine\GuardEngine;
use RenzoFranceschini\GuardCoreSlim\SlimGuard;
use Slim\App;

// The coverage runner requires composer's autoloader for php-code-coverage
// before the suite runs; drop it again so the soft reference in
// src/SlimGuard.php cannot resolve (composer's classmap knows the slim/psr7
// dev dependency even though this suite simulates its absence). The coverage
// driver's data class is resolved eagerly first: the runner reports coverage
// from a shutdown function, which runs after this unregistration.
class_exists(\SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData::class);
foreach (spl_autoload_functions() ?: [] as $loader) {
    spl_autoload_unregister($loader);
}

require __DIR__ . '/../tests/scoped_autoloader.php';
final class T
{
    public int $passed = 0;

    public int $failed = 0;

    public function ok(bool $condition, string $label): void
    {
        if ($condition) {
            $this->passed++;
            echo "ok - {$label}\n";
        } else {
            $this->failed++;
            echo "FAIL - {$label}\n";
        }
    }

    public function same(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            $this->passed++;
            echo "ok - {$label}\n";
        } else {
            $this->failed++;
            echo "FAIL - {$label}\n";
            echo '  expected: ' . var_export($expected, true) . "\n";
            echo '  actual:   ' . var_export($actual, true) . "\n";
        }
    }

    public function throws(string $class, callable $fn, string $label): void
    {
        try {
            $fn();
            $this->failed++;
            echo "FAIL - {$label}: no exception\n";
        } catch (Throwable $e) {
            $this->same($class, $e::class, $label);
        }
    }

    public function finish(): never
    {
        $total = $this->passed + $this->failed;
        echo "\nPassed: {$this->passed}, Failed: {$this->failed}\n";
        echo "{$this->passed}/{$total}" . ($this->failed === 0 ? ' GREEN' : ' RED') . "\n";
        exit($this->failed === 0 ? 0 : 1);
    }
}

/**
 * A PSR-7 implementation WITHOUT a stream factory (the shape of a host app on
 * e.g. Nyholm/Guzzle factories wired response-only). createResponse is never
 * reached: the suite only resolves factories, it never dispatches a request.
 */
final class ResponseOnlyFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new RuntimeException('not used by this suite');
    }
}

final class ExplicitStreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        throw new RuntimeException('not used by this suite');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new RuntimeException('not used by this suite');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new RuntimeException('not used by this suite');
    }
}

$t = new T();

$t->ok(
    !class_exists(\Slim\Psr7\Factory\StreamFactory::class),
    'suite booted with slim/psr7 unresolvable (soft reference cannot autoload)'
);

$t->throws(
    LogicException::class,
    static function (): void {
        $app = new App(new ResponseOnlyFactory());
        SlimGuard::forApp($app, new GuardEngine(new SecurityConfig(enableRedis: false)));
    },
    'no stream factory anywhere and no slim/psr7: forApp fails with the documented LogicException'
);

try {
    $app = new App(new ResponseOnlyFactory());
    SlimGuard::forApp($app, new GuardEngine(new SecurityConfig(enableRedis: false)));
    $message = '';
} catch (LogicException $e) {
    $message = $e->getMessage();
}
$t->ok(
    str_contains($message, 'Pass a StreamFactoryInterface explicitly'),
    'LogicException carries the remediation guidance for explicit factories'
);

$guard = SlimGuard::forApp(
    new App(new ResponseOnlyFactory()),
    new GuardEngine(new SecurityConfig(enableRedis: false)),
    new ExplicitStreamFactory()
);
$t->ok($guard->middleware() instanceof \Psr\Http\Server\MiddlewareInterface, 'explicit stream factory: forApp composes the PSR-15 middleware without any slim/psr7 fallback');

$t->finish();
