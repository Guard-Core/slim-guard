Release Notes
=============

___

v1.4.0 (2026-10-07)
-------------------

The engine-repin release: guard-core-php ^4.3.1 from packagist plus agent wiring and the JSON status route through the psr15-guard composition (v1.4.0)
-------------------------------------------------------------------------------------------------------------------------------------------------------

### Changed

- **Engine constraint repinned to guard-core-php ^4.3.1, the shipped parity release.** `composer.json` floors `rennf93/guard-core-php` at the released `^4.3.1` and `composer.lock` resolves it at v4.3.1 from packagist, replacing the dev-branch pin; the CI engine-checkout overrides are dropped so the gates run against the shipped engine, and everything resolves from the registry with no path or VCS repository entries. The composed `rennf93/psr15-guard` stays floored at ^1.3.0 (1.4.0 satisfies the range), carrying the longest-path route-config resolution through the unchanged factory wiring.

### Added

- **FP-PHP parity surface (PR #24).** `SlimGuard::forApp` accepts an optional `agentHandler` wired into the engine's event bus (duck-typed `sendEvent`). `statusRoute()` registers `GET /_guard/status` serving `GuardEngine::initializationStatus()` as JSON, mirroring fastapi-guard's status route.

### Verification

- Local gates on php 8.5.11: `composer validate` exit 0, `composer audit --locked` clean (zero open advisories), `make lint` exit 0, `bin/test_slim.php` 81/81 checks green and `bin/test_slim_no_psr7.php` 4/4 green with host Redis on 6379 (includes the new parity assertions), with `composer.lock` resolving guard-core-php v4.3.1 and psr15-guard v1.3.0. PHPStan level 5 via the `ghcr.io/phpstan/phpstan:latest` image: no errors. Dockerized live smoke (examples/simple_app, port 8080): all six workflow assertions green.

___

v1.3.0 (2026-10-01)
-------------------

### Changed

- **Both floors rise: `rennf93/guard-core-php` ^4.3.0 and `rennf93/psr15-guard` ^1.3.0.** 1.3.0 tracks the safety-corpus train: the engine ships the spec 12 event bus with dynamic rules and Redis-backed metrics persistence, the spec 10 geo download and refresh lifecycle, the spec 04 ReDoS safety gates over PCRE plus the performance monitor, and the pattern_safety / events / redis_interop conformance runners, while the composed psr15-guard 1.3.0 floors that engine and carries the process scaffold and the coverage gate. Nothing in the Slim surface changes: SlimGuard composes the psr15-guard middleware, so the new engine surfaces ride config through the unchanged factory wiring. The CI sibling version stamps move with the floors (engine 4.2.99 -> 4.3.99, psr15-guard 1.2.99 -> 1.3.99 in ci.yml, release.yml, static-analysis.yml, upstream-drift.yml, scheduled-lint.yml) so the floors resolve against the master siblings while the published constraints stay ^4.3.0/^1.3.0, and `composer.lock` resolves both packages at their released v4.3.0 / v1.3.0.

### Added

- **Release Gate and lock fixes (PRs #18, #19).** The v1.2.0 tag's Release Gate failed at Install dependencies with `Source path ../psr15-guard is not found` (the 1.2.0 release added the psr15-guard ^1.2.0 dependency and the ../psr15-guard path repository, and learned ci.yml the sibling checkout and the 1.2.99 stamp, but release.yml never got them): both release jobs (test matrix and composer-audit) now mirror the ci.yml pattern - check out psr15-guard@master into the sibling location, version-stamp it next to the engine, keep the two path repositories while dropping only the eager VCS entries, and composer update both path packages on install. The weekly Scheduled Lint was unbroken separately (PR #19): the committed composer.lock pinned the dev-stamp versions `guard-core-php 4.2.99` / `psr15-guard 1.2.99`, which exist in no registry, so `composer install` could not resolve psr15-guard from Packagist; the lock regenerated against the released versions and the scheduled-lint workflow itself was already correct (the engine path sibling resolves the stamped dev version via the existing composer update step).
- **Process scaffold (PR #20).** `SECURITY.md` (supported versions, advisory reporting), `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `.github/FUNDING.yml`, the PR template and the issue templates, modeled on the Go family and the fastapi-guard baseline. `.github/workflows/static-analysis.yml`: phpstan via the docker pattern these repos already use (composer:2 install with the same ci-only path-repository patch as ci.yml, then `ghcr.io/phpstan/phpstan:latest analyse src --level=5`), on push/pr to master plus the weekly Monday cron; level 5 is the highest level with zero findings on master (level 6 reports one generic return type). `.github/workflows/live-smoke.yml` gains the `live-smoke-advanced` job (the existing simple_app job untouched): a dockerized run of `examples/advanced_app` on host port 8081 asserting `GET /` and `GET /health` 200 plus one blocked-request check (`GET /admin/check?ip=203.0.113.9` without `X-Admin-Token` returns the admin gate's 400 "Missing required header: X-Admin-Token"). CodeQL was drafted and dropped after the first run proved GitHub CodeQL no longer supports PHP (`Did not recognize the following languages: php`); `.github/workflows/semgrep.yml` is the substitute, running the pinned semgrep/semgrep:1.177.0 container with the p/security-audit and p/secrets rulesets over src/ (path-filtered push/PR triggers plus a weekly Monday cron, metrics off).
- **100% line coverage gate (PR #21, following the psr15-guard pilot).** The bespoke `bin/test_*.php` suites stay as they are; `.github/coverage-runner.php` (phpunit/php-code-coverage ^11 + pcov) executes each suite under coverage in collect mode and a merge pass gates on 100.00% measured lines in src/. Coverage went from 70.59% to 100.00% lines (pcov, php 8.3): `bin/test_slim.php` (81 assertions) plus the new `bin/test_slim_no_psr7.php` (4 assertions) covering the documented no-slim/psr7 fallback in `SlimGuard::resolveStreamFactory()` - composer's autoloader cannot reach that branch (slim/psr7 is a dev dependency, so the soft class_exists reference always resolves in a composer-booted process), so the new suite boots through `tests/scoped_autoloader.php` with no composer autoloader and no slim/psr7 mapping, reproducing the production shape the branch exists for. The coverage job mirrors ci.yml's path-repo/stamp resolution and suite pass/fail stays gated by the existing test job.

### Verification

- `make lint` exit 0 and `make test` exit 0 on php 8.5.11 (host Redis on 6379, 81/81 checks green), with `composer.lock` resolving guard-core-php v4.3.0 and psr15-guard v1.3.0.

___

v1.2.0 (2026-09-27)
-------------------

### Changed

- **The parity surface goes live and the floors rise: `rennf93/guard-core-php` ^4.2.0 and `rennf93/psr15-guard` ^1.2.0 (both released at the 4.2.0 train).** The 4.1.0 family tags were a version-accuracy error and were yanked/unpublished, so the published ^4.1.0/^1.0.0 floors do not resolve publicly over Packagist; 1.2.0 restores publicly resolvable floors. The `pending-psr15-release` feature-detect skips are removed from `bin/test_slim.php`: the pass-through finish, the routes map and the geo rate-limit resolver are now exercised unconditionally, and a resolution without the psr15-guard parity API is a hard failure instead of a skip. CI keeps the version-stamped sibling path checkouts (engine 4.2.99, psr15-guard 1.2.99) while the published constraints are ^4.2.0/^1.2.0.

v1.1.0 (unreleased)
-------------------

### Added

- **Parity-surface readiness (pending-psr15-release).** CI now checks out `rennf93/psr15-guard` master as a version-stamped path repository (the wave-4 guard-core-php pattern) alongside the engine, so the incoming pass-through/routes/geo parity surface is continuously exercised through the composed middleware; `bin/test_slim.php` carries feature-detected reachability tests for that surface that run when the installed psr15-guard carries it and skip with an explicit `pending-psr15-release` message over the released Packagist resolution (76 checks green on the sibling stack). The surface lands for slim with the psr15-guard release at the 4.2.0 train, when the composer constraint bumps; documented in the README and `docs/configuration.md`.

___

v1.0.0 (2026-09-24)
-------------------

First stable release (v1.0.0)
-----------------------------

### Added

- **Slim 4 adapter for guard-core-php 4.0.4.** slim-guard composes the psr15-guard PSR-15 middleware into Slim's App and adds the Slim-native integration layer; it contains no security logic and no detection logic of its own. Every verdict comes from `GuardEngine::execute()`, and the fail-secure path (block translation, fail-closed 500) is inherited from psr15-guard, not reimplemented here.
- **Factory wiring.** `SlimGuard::forApp($app, $engine)` takes the `ResponseFactoryInterface` from Slim's `App` and resolves a matching `StreamFactoryInterface` (explicit argument, response factory that is also a stream factory, or the default slim/psr7 implementation when installed).
- **Attachment.** `addTo($app)` calls Slim's `App::add()`; `addToGroup($group)` attaches to a route group with its documented semantics.
- **Body-parsing ordering guidance** for Slim's optional `BodyParsingMiddleware`, which consumes the request body stream.

### Changed

- **The engine dependency is pinned to `rennf93/guard-core-php` `^4.0.4`** and the composed PSR-15 adapter to `rennf93/psr15-guard` `^1.0.0`, both first stable releases, replacing the `^0.1.0` pins to the burned pre-release snapshot tags.

### Internal (v1.0.0)

- Unit and Redis-backed integration suites for the Slim wiring layer in `bin/test_slim.php` (Redis-backed shared-state cases run when `REDIS_HOST` points at a reachable Redis), plus a `php -l` sweep and `composer audit` in CI across PHP 8.2, 8.3 and 8.4.
- Community workflows, a MkDocs documentation site, and example apps landed via the parity-polish pass.

___
