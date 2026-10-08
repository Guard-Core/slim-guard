# slim-guard

Slim 4 adapter for [guard-core-php](https://github.com/Guard-Core/guard-core-php): wires the guard into Slim's middleware stack. It composes [psr15-guard](https://github.com/Guard-Core/psr15-guard) (the PSR-15 middleware adapter for the same engine) and adds the Slim-native integration layer: PSR-7 factory wiring from Slim's `App`, one-call attachment to the app or a route group, and the body-parsing ordering guidance. Works with Slim 4.

Docs: <https://guard-core.github.io/slim-guard/>

This package contains no security logic and no detection logic of its own. Every verdict comes from `GuardEngine::execute()`, and the fail-secure path (block translation, fail-closed 500) is inherited from psr15-guard, not reimplemented here.

## Design: composition, not duplication

Slim 4 middleware IS PSR-15 middleware (`psr/http-server-middleware`), and psr15-guard already provides exactly that. slim-guard does not copy it. It requires psr15-guard and contributes only what is Slim-specific:

- Factory wiring: `SlimGuard::forApp($app, $engine)` takes the `ResponseFactoryInterface` from Slim's `App` and resolves a matching `StreamFactoryInterface` (explicit argument, response factory that is also a stream factory, or the default slim/psr7 implementation when installed).
- Attachment: `addTo($app)` calls Slim's `App::add()`; `addToGroup($group)` attaches to a route group with its documented semantics.
- Ordering guidance for Slim's optional `BodyParsingMiddleware`, which consumes the request body stream.

The relationship is deliberate: slim-guard is the ecosystem's Slim flavor of the PSR-15 adapter, and psr15-guard remains the single place where guard verdicts become PSR-7 responses.

## Install

```bash
composer require rennf93/slim-guard
```

## Usage

Attach the guard where your Slim `App` is assembled:

```php
use RenzoFranceschini\GuardCore\Config\SecurityConfig;
use RenzoFranceschini\GuardCore\Engine\GuardEngine;
use RenzoFranceschini\GuardCoreSlim\SlimGuard;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

SlimGuard::forApp($app, new GuardEngine(new SecurityConfig(
    enableRedis: false,
    blacklist: ['192.0.2.0/24'],
    rateLimit: 100,
    rateLimitWindow: 60,
    enableRateLimiting: true,
)))->addTo($app);
```

Blocked requests get the engine's block verdict translated to a PSR-7 response by psr15-guard: `403 Forbidden` for a blacklisted IP, `429 Too many requests` with `Retry-After` for a rate limit hit. Passing requests continue into Slim's middleware stack untouched.

## Lifecycle

PHP shared-nothing applies: construct `GuardEngine` per request in classic FPM, or per worker under long-running runtimes (FrankenPHP, RoadRunner, workerman). `SlimGuard` and the composed psr15-guard middleware hold no mutable state of their own. In-memory fallbacks are per-request safety nets; distributed rate limits, IP bans, and cloud-range caches require Redis (set `enableRedis: true` and point `REDIS_HOST`/`REDIS_PORT` at your instance).

## Behavior notes

- Fail-closed is inherited from psr15-guard: if the engine throws, the composed middleware returns the engine's fail-closed response (`500 Security check failed`, honorably overridden by `customErrorResponses`) instead of letting the request through.
- Bounded body read is inherited from psr15-guard: the request body is scanned as a prefix of at most 256 KiB (matching the engine's full-scan window). Payloads beyond the prefix, or signatures split across its boundary, are not detected.
- Middleware order matters: add the guard so it executes before any middleware that reads the request body stream (Slim's `BodyParsingMiddleware` casts the stream to a string, which leaves the read pointer at EOF). In Slim, the last middleware added executes first.
- App-level attachment (`addTo`) screens everything that reaches the app, including unmatched routes that would 404. Group-level attachment (`addToGroup`) only screens requests that match a route inside the group; a blacklisted IP hitting an unmatched path under the group is answered by Slim's 404, not the guard's 403.
- No security headers or CORS are added by this adapter or by psr15-guard; blocked responses are exact engine translations.

## Testing

```bash
composer lint
composer test
```

`composer test` runs the plain-PHP suite in `bin/test_slim.php` (unit coverage always; set `REDIS_HOST` to a reachable Redis to include the shared-state integration cases).

## Status

Released: v1.1.0 on Packagist. The engine floor is `rennf93/guard-core-php ^4.1.0`; no 4.1.0 of the engine is currently published (its tag is absent and Packagist's latest is v4.0.4), so public resolution is collapsed until the synchronized 4.2.0 train retags the engine - CI resolves the engine from the master sibling checkout in the meantime. The PSR-15 adapter it composes, `rennf93/psr15-guard`, is at v1.1.0 on Packagist; the pass-through parity surface below is merged on its master and lands in its next release at the 4.2.0 train.

## Known gaps (pending-psr15-release)

The pass-through parity surface (engine security headers and CORS verdict headers on the handler response, behavioral return rules over a bounded response-body prefix, the per-route `routes` map, and the geo rate-limit resolver) lives on the composed `rennf93/psr15-guard` middleware, whose released 1.x does not carry it yet. This is a composition-chain fact, not a missing capability: the PSR-15 request state (route config, client ip) is created inside psr15-guard's `process()`, so slim cannot wire it wrapper-side without duplicating security logic. The surface lands with the psr15-guard release at the 4.2.0 train, when slim bumps its constraint. CI already mounts the psr15-guard master sibling, and `bin/test_slim.php` exercises the surface there (it skips with an explicit `pending-psr15-release` message over the released Packagist resolution).

## License

MIT
