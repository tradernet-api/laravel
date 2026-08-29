<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tradernet\Laravel\Facades\Tradernet;
use Tradernet\Laravel\TradernetServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param mixed $app
     */
    protected function defineEnvironment($app): void
    {
        Config::set('tradernet.default', 'main');
        Config::set('tradernet.connections.main', [
            'api_key' => 'test-public-key',
            'api_secret' => 'test-private-key',
            'login' => null,
            'password' => null,
            'password_resolver' => null,
            'domain' => 'https://tradernet.com',
            'lang' => 'en',
            'auth_mode' => 'keys_only',
            'timeout' => 30.0,
            'user_agent' => null,
            'sid_cookie' => 'SID',
            'sid_ttl' => 1_209_600,
            'reauth' => [
                'max_attempts' => 3,
                'window_seconds' => 900,
                'open_seconds' => 900,
            ],
            'session' => [
                'driver' => 'memory',
                'store' => null,
                'prefix' => 'tradernet:sid:',
                'encrypt' => false,
                'lock_ttl' => 90,
                'lock_wait' => 20,
                'meta_ttl' => 3600,
            ],
        ]);

        Config::set('cache.default', 'file');
        Config::set('cache.stores.file', [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ]);
    }

    /**
     * @param mixed $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Tradernet' => Tradernet::class,
        ];
    }

    /**
     * @param mixed $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TradernetServiceProvider::class,
        ];
    }

    /**
     * Non-null, contract-typed application.
     *
     * Testbench declares $app as nullable, which every call site would
     * otherwise have to narrow by hand.
     */
    protected function laravel(): Application
    {
        $app = $this->app;

        if ($app === null) {
            self::fail('Application was not booted.');
        }

        return $app;
    }
}
