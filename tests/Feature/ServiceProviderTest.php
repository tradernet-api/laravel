<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Tradernet\Laravel\Facades\Tradernet as TradernetFacade;
use Tradernet\Laravel\Tests\TestCase;
use Tradernet\Laravel\TradernetManager;
use Tradernet\Laravel\TradernetServiceProvider;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Tradernet;

final class ServiceProviderTest extends TestCase
{
    public function testBindsSdkClientAndManager(): void
    {
        $client = $this->laravel()->make(Tradernet::class);
        $manager = $this->laravel()->make(TradernetManager::class);

        self::assertInstanceOf(Tradernet::class, $client);
        self::assertInstanceOf(TradernetManager::class, $manager);
        self::assertSame($client, $manager->connection());
        self::assertSame(AuthMode::KEYS_ONLY, $client->config()->authMode);
    }

    public function testFacadeResolvesDefaultConnection(): void
    {
        $viaFacade = TradernetFacade::connection();
        $viaContainer = $this->laravel()->make(Tradernet::class);

        self::assertSame($viaContainer, $viaFacade);
        self::assertSame('https://tradernet.com', TradernetFacade::config()->domain);
    }

    public function testNamedConnectionIsIsolated(): void
    {
        Config::set('tradernet.connections.demo', [
            'api_key' => 'demo-public-key',
            'api_secret' => 'demo-private-key',
            'login' => null,
            'password' => null,
            'password_resolver' => null,
            'domain' => 'https://tradernet.com',
            'lang' => 'ru',
            'auth_mode' => 'keys_only',
            'timeout' => 15.0,
            'user_agent' => 'tradernet-laravel-test/0.1',
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
                'prefix' => 'tradernet:sid:demo:',
                'encrypt' => false,
                'lock_ttl' => 90,
                'lock_wait' => 20,
                'meta_ttl' => 3600,
            ],
        ]);

        $main = TradernetFacade::connection('main');
        $demo = TradernetFacade::connection('demo');

        self::assertNotSame($main, $demo);
        self::assertSame('en', $main->config()->lang);
        self::assertSame('ru', $demo->config()->lang);
        self::assertSame(15.0, $demo->config()->timeout);
    }

    /**
     * A deferred provider is never booted during bootstrap, so publishes()
     * would not run and vendor:publish --tag=tradernet-config would find
     * nothing. Testbench registers package providers eagerly and hides that,
     * hence the explicit isDeferred() assertion.
     */
    public function testProviderIsNotDeferredSoConfigIsPublishable(): void
    {
        $provider = new TradernetServiceProvider($this->laravel());

        self::assertFalse($provider->isDeferred());
        self::assertContains('tradernet-config', ServiceProvider::publishableGroups());
    }

    public function testPurgeAlsoRebuildsTheContainerBinding(): void
    {
        $before = $this->laravel()->make(Tradernet::class);
        self::assertSame('en', $before->config()->lang);

        Config::set('tradernet.connections.main.lang', 'ru');
        $this->laravel()->make(TradernetManager::class)->purge();

        $after = $this->laravel()->make(Tradernet::class);

        self::assertNotSame($before, $after);
        self::assertSame('ru', $after->config()->lang);
    }
}
