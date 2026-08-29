# AGENTS.md

## Package

| | |
|---|---|
| Composer | `tradernet/laravel` |
| Repo / folder | `laravel-tradernet` |
| Namespace | `Tradernet\Laravel` |
| Depends on | `tradernet/sdk` (`Tradernet\Sdk`) — do not vendor or copy SDK sources |

## Purpose

Laravel glue for Tradernet PHP SDK: ServiceProvider, config publish, facade, named connections (`TradernetManager`), and cache-backed `SessionStorageInterface` with distributed locks.

## Public surface

| Component | Role |
|---|---|
| `TradernetServiceProvider` | Registers bindings; publishes `config/tradernet.php`. **Not** deferrable |
| `TradernetManager` | Named connections (scoped); `__call` forwards to default client |
| `Facades\Tradernet` | Facade → manager |
| `Config\ConfigMapper` | Laravel config → `Credentials` + `ClientConfig` |
| `Exceptions\ConnectionNotConfiguredException` | Missing connection / missing API key pair |
| `Session\CacheSessionStorage` | Encrypted SID in cache; unencrypted reauth meta under a separate key; re-entrant locks |
| `Session\CacheSessionLock` | `SessionLockInterface` adapter over a release callback |
| `Session\SessionStorageFactory` | `cache` / `memory` / `null` (+ `keys_only` → memory) |

## Rules

- No HTTP clients, API resources, auth, or signing in this package — call the SDK.
- Persist sessions with `Session::toArray()`, never `jsonSerialize()` (masks SID).
- Committed `composer.json` must stay Packagist-ready: `tradernet/sdk: ^0.1`, no `repositories`, `minimum-stability: stable`.
- Local sibling SDK: temporary `composer config repositories.sdk path ../TradernetPhpSDK`, then `git checkout -- composer.json` before push. See `docs/publishing.md`.
- Prefer DI of `Tradernet\Sdk\Tradernet`; facade is optional DX.
- Never make the provider a `DeferrableProvider`: deferred providers are not booted at bootstrap, so `vendor:publish --tag=tradernet-config` would find nothing.
- `Tradernet` is bound with `bind()`, not `singleton()`/`scoped()`. `TradernetManager` owns the only client cache, so `purge()` stays a single reset point for DI and facade.
- The `cache` session store must survive the process: `array` and `null` are rejected explicitly, because both implement `LockProvider` yet break `ReauthGuard` rate limiting.
- Reuse `Tradernet\Sdk\Support\Cast` for mixed config values instead of raw casts.
- Update this file when adding/removing providers, session drivers, or artisan commands.

## Tests

Orchestra Testbench. Run: `composer test`, `composer stan` (PHPStan level max), `composer cs` (php-cs-fixer dry run).
