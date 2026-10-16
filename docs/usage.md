# Usage

## Construction

```php
final class SlimGuard
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        GuardEngine $engine,
        ?SecurityDecorator $decoratorHandler = null,
        ?object $agentHandler = null
    ) { ... }

    public static function forApp(
        App $app,
        GuardEngine $engine,
        ?StreamFactoryInterface $streamFactory = null,
        ?object $agentHandler = null,
        ?SecurityDecorator $decoratorHandler = null
    ): self;
    public function middleware(): MiddlewareInterface;
    public function addTo(App $app): App;
    public function addToGroup(RouteGroupInterface $group): RouteGroupInterface;
    public function statusRoute(App $app, string $path = '/_guard/status'): void;
    public function agentStats(): array;
    public function reset(): void;
    public function refreshCloudIpRanges(): void;
}
```

`SlimGuard::forApp()` builds the guard from an existing Slim `App`: the
response factory is the app's own, and the stream factory resolves from the
explicit argument, then a response factory that also implements
`StreamFactoryInterface`, then the default slim/psr7 factory when installed. A
`LogicException` is thrown when none of those apply; pass a
`StreamFactoryInterface` explicitly instead of relying on slim/psr7.

The constructor composes the psr15-guard `GuardMiddleware`, which calls
`$engine->initialize()`; a `GuardRedisException` is swallowed only when
`SecurityConfig.redisFailOpen` is true, otherwise construction fails closed.

## Attachment

```php
SlimGuard::forApp($app, $engine)->addTo($app);        // app middleware: screens everything, including 404s

$group = $app->group('/admin', function ($group) { ... });
SlimGuard::forApp($groupApp, $adminEngine)->addToGroup($group);
                                                       // group middleware: only screens matched routes
```

`middleware()` exposes the composed PSR-15 middleware for hand wiring
(`$app->add($slimGuard->middleware())`) or for any stack that accepts PSR-15
middleware.

## Middleware ordering

Slim runs the last-added middleware first. Add the guard AFTER (in code order)
any middleware that reads the request body stream: Slim's optional
`BodyParsingMiddleware` casts the stream to a string, which leaves the read
pointer at EOF and starves the engine's bounded body scan. The guard should be
added last so it executes first.

## Verdicts

Block verdicts are translated by the composed psr15-guard middleware and never
reach your route callable:

| Situation | Status | Body |
|---|---|---|
| Banned IP | 403 | `IP address banned` |
| Suspicious content | 400 | `Suspicious activity detected` |
| Rate limit exceeded | 429 | `Too many requests` plus `Retry-After` |
| Engine malfunction | 500 | `Security check failed` |

Bodies can be overridden globally through `SecurityConfig.customErrorResponses`.

## Telemetry agent and dynamic rules

The adapter forwards an optional `agentHandler` to the engine (`GuardEngine::setAgentHandler()`), so security events, metrics and dashboard dynamic rules flow through [guard-agent-php](https://github.com/Guard-Core/guard-agent-php). Install it with `composer require rennf93/guard-agent-php`, create and start the agent where the Slim `App` is assembled, pass it as the `agentHandler` argument, and declare it in the config with `enableAgent: true`:

```php
use RenzoFranceschini\GuardAgent\GuardAgent;
use RenzoFranceschini\GuardCore\Config\SecurityConfig;
use RenzoFranceschini\GuardCore\Engine\GuardEngine;

$agent = new GuardAgent([
    'apiKey' => $_ENV['GUARD_API_KEY'],
    'endpoint' => 'https://api.guard-core.com',
    'projectId' => 'my-project',
]);
$agent->start();

$config = new SecurityConfig(
    enableRedis: true,
    enableAgent: true,
    enableDynamicRules: true,
    dynamicRuleInterval: 300,
    enableEnrichment: true,
    agentProjectId: 'my-project',
);

SlimGuard::forApp($app, new GuardEngine($config), agentHandler: $agent)->addTo($app);

// or with the plain constructor
$guard = new SlimGuard($app->getResponseFactory(), $streamFactory, new GuardEngine($config), agentHandler: $agent);

register_shutdown_function(static function () use ($agent): void {
    $agent->flushBuffer();
    $agent->stop();
});
```

`enableDynamicRules` and `enableEnrichment` require `enableAgent`, and dynamic rules need an agent handler that exposes `getDynamicRules()` (`GuardAgent` does). The engine builds the dynamic rule manager itself and checks for new rules on the first request and then every `dynamicRuleInterval` seconds (default 300, minimum 60), because a PHP request process has no background loop. Without an agent handler the engine warns and stays on the static config. `agentStats()` on the adapter reports `{enabled: false, degraded: false}` without a handler and merges the handler's `getStats()` map with one.

## Response modifier

`SecurityConfig::customResponseModifier` is a `Closure(GuardResponse): GuardResponse` that the engine applies once to every response it returns itself (blocks, redirects and the fail-closed response), after the security headers and before the CORS verdict headers. This adapter applies the same modifier to the pass-through responses it sends, through `GuardEngine::applyResponseModifier()`, including excluded-path responses and responses of requests that bypass all checks, and it never applies it twice to a response the engine already modified. The order on a pass-through response is security headers, modifier, then CORS verdict headers, so the CORS policy has the last word on its own headers.

```php
use RenzoFranceschini\GuardCore\Request\GuardResponse;

$config = new SecurityConfig(
    customResponseModifier: static function (GuardResponse $response): GuardResponse {
        $response->headers()->set('X-Served-By', 'edge-1');

        return $response;
    },
);
```

The modifier sees the status, the headers (`Set-Cookie` is not exposed) and the body, and the changes are merged back onto the PSR-7 response; a response whose body stream is not seekable keeps its body (only its status and headers can change). A modifier that throws leaves the response unmodified. Nothing is read from the response while no modifier is configured. The modifier seam needs guard-core-php 4.4.0 or later.

## Fail-closed behavior

Inherited from psr15-guard: if the engine check throws, the middleware
responds with the engine's fail-closed response (`500 Security check failed`)
rather than letting the request through. A custom 500 body comes from
`customErrorResponses[500]`, not from adapter code.

## Route-scoped configuration

The engine supports per-route `RouteConfig` (required headers, per-route rate
limits) keyed off a route ID on the request state, and the composed adapter
builds its engine request internally, so there is no route-ID hook. The
engine-sanctioned equivalent is the `customRequestCheck` config closure, which
runs as the last pipeline check; for Slim-native route scoping, attach a
dedicated guard to a route group with `addToGroup()` and its own engine
instance. See the
[advanced example app](https://github.com/Guard-Core/slim-guard/tree/master/examples/advanced_app).
