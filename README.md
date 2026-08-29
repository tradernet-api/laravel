# Tradernet Laravel

[![PHP](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Laravel integration for [`tradernet/sdk`](https://github.com/tradernet-api/TradernetPhpSDK): config, container bindings, facade, and cache-backed SID session storage.

| | |
|---|---|
| **Package** | `tradernet/laravel` |
| **Repository** | `laravel-tradernet` |
| **Namespace** | `Tradernet\Laravel` |
| **PHP** | `^8.3` |
| **Laravel** | `11` / `12` / `13` |

This package does **not** reimplement the HTTP API. It depends on `tradernet/sdk` and adds Laravel glue only.

## Installation

```bash
composer require tradernet/laravel
```

Publish config:

```bash
php artisan vendor:publish --tag=tradernet-config
```

## Configuration

Reuse the same env vars as the PHP SDK:

```env
TRADERNET_API_KEY=
TRADERNET_API_SECRET=
TRADERNET_LOGIN=
TRADERNET_PASSWORD=
TRADERNET_DOMAIN=https://tradernet.com
TRADERNET_AUTH_MODE=sid_lazy
```

Optional Laravel-specific:

```env
TRADERNET_CONNECTION=main
TRADERNET_SESSION_DRIVER=cache
TRADERNET_SESSION_STORE=tradernet
TRADERNET_SESSION_ENCRYPT=true
```

Prefer a **dedicated** cache store for SID sessions so `php artisan cache:clear` on the default store does not wipe sessions (ReauthGuard may then block re-login for ~15 minutes).

## Usage

Dependency injection (preferred):

```php
use Tradernet\Sdk\Tradernet;

public function __invoke(Tradernet $tn)
{
    return $tn->quotes()->get(['AAPL.US']);
}
```

Facade:

```php
use Tradernet\Laravel\Facades\Tradernet;

$quotes = Tradernet::quotes()->get(['AAPL.US']);
$demo = Tradernet::connection('demo');
```

### Named connections

```php
// config/tradernet.php
'connections' => [
    'main' => [ /* ... */ ],
    'demo' => [ /* other keys / domain */ ],
],
```

### Session storage

| Driver | When |
|---|---|
| `cache` (default) | Multi-server / Horizon — needs a store with atomic locks (Redis recommended) |
| `memory` | Tests / single process |
| `null` | No SID persistence |

`keys_only` auth mode always uses in-memory storage (SID is never obtained).

The `cache` driver **rejects the `array` and `null` cache stores** at construction. Both implement Laravel's `LockProvider` but do not persist across processes, so `ReauthGuard` would stop counting `authByLogin` attempts and could let the cabinet be locked out. Use `memory` as the session driver in tests instead.

**Do not** put a `Tradernet` client (or `Credentials`) into queued job properties — SDK credentials refuse serialization. Resolve the client from the container inside `handle()`.

**WebSocket** streams from the SDK need an event loop — run them from a long-lived Artisan command / supervisor process, not from an HTTP request or typical queue job.

## Local development

Clone next to `TradernetPhpSDK`. The committed `composer.json` expects Packagist (`tradernet/sdk: ^0.1`).

Until Packagist is live (or for local SDK edits), temporarily link the sibling — **do not commit** the change:

```bash
composer config repositories.sdk path ../TradernetPhpSDK
composer update tradernet/sdk
composer test
composer stan
composer cs
git checkout -- composer.json   # restore publishable composer.json before push
```

Maintainer release steps: [docs/publishing.md](docs/publishing.md).

## License

[MIT](LICENSE) © Tradernet API Support \<tradernet.com\>
