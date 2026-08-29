<?php

declare(strict_types=1);

namespace Tradernet\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Tradernet\Laravel\Config\ConfigMapper;
use Tradernet\Laravel\Session\SessionStorageFactory;
use Tradernet\Sdk\Tradernet;

/**
 * Registers Tradernet bindings and publishes the package config.
 *
 * Deliberately not a DeferrableProvider: deferred providers are never booted
 * during bootstrap, so publishes() would never run and
 * `vendor:publish --tag=tradernet-config` would find nothing. register() only
 * stores closures, and mergeConfigFrom() is a no-op once config is cached.
 */
final class TradernetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tradernet.php' => config_path('tradernet.php'),
            ], 'tradernet-config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tradernet.php', 'tradernet');

        $this->app->singleton(ConfigMapper::class);
        $this->app->singleton(SessionStorageFactory::class);

        // scoped(): Octane rebuilds the client cache per request, so a reloaded
        // config never serves a stale client and no lock state outlives a crash.
        $this->app->scoped(TradernetManager::class);

        // bind(), not singleton/scoped: the manager owns the only client cache,
        // which keeps purge() a single reset point for DI and facade alike.
        $this->app->bind(Tradernet::class, static function (Application $app): Tradernet {
            /** @var TradernetManager $manager */
            $manager = $app->make(TradernetManager::class);

            return $manager->connection();
        });

        $this->app->alias(Tradernet::class, 'tradernet');
    }
}
