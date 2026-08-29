# Publishing `tradernet/laravel` (Packagist)

**Order matters:** publish [`tradernet/sdk`](https://github.com/tradernet-api/TradernetPhpSDK) first (`v0.1.0` + Packagist), then this package. Otherwise `composer require tradernet/laravel` cannot resolve `tradernet/sdk:^0.1`.

SDK guide: `../TradernetPhpSDK/docs/publishing.md` (or the same file in the SDK repo).

## Prerequisites

- `tradernet/sdk` already on Packagist with a `0.1.*` release
- GitHub push access to `tradernet-api/laravel`
- Packagist account that can claim `tradernet/laravel`

## 1. Ensure publishable `composer.json`

Committed `composer.json` must have:

- `"tradernet/sdk": "^0.1"` (not `dev-main` / `@dev`)
- **no** `repositories` section (path/VCS here is ignored for consumers and breaks expectations)
- `"minimum-stability": "stable"`
- auto-discovery under `extra.laravel`

Local sibling SDK (before Packagist / while hacking SDK) — temporary, never commit:

```bash
composer config repositories.sdk path ../TradernetPhpSDK
composer update tradernet/sdk
composer test
composer stan
composer validate --strict
git checkout -- composer.json
```

With Packagist already live:

```bash
composer update tradernet/sdk
composer test
composer stan
composer validate --strict
```

## 2. Tag `v0.1.0`

```bash
cd /Users/morg01hgmail.com/PhpstormProjects/laravel-tradernet
git checkout main
git pull origin main
git tag -a v0.1.0 -m "tradernet/laravel v0.1.0"
git push origin main
git push origin v0.1.0
```

## 3. Submit to Packagist

1. [https://packagist.org/packages/submit](https://packagist.org/packages/submit)
2. URL: `https://github.com/tradernet-api/laravel`
3. Confirm name `tradernet/laravel`
4. Attach GitHub webhook (same pattern as SDK) so tags auto-sync

## 4. Verify end-to-end

In any Laravel app (including Sail):

```bash
composer require tradernet/laravel
# or
./vendor/bin/sail composer require tradernet/laravel
```

No `repositories` / `dev-main` needed in the app.

```bash
php artisan vendor:publish --tag=tradernet-config
```

## Packagist how-to (short)

| Step | Where |
|---|---|
| Account | packagist.org → Login with GitHub |
| Submit package | Packages → Submit → paste GitHub URL |
| Auto-update | Package → GitHub Hook / webhook with Packagist API token |
| Manual refresh | Package page → Update |
| Vendor name | Must match `composer.json` `name` (`tradernet/...`). First package under a vendor may require vendor approval on Packagist |

If submit says vendor `tradernet` is reserved, claim it via Packagist support or an existing maintainer of `tradernet/*`.
