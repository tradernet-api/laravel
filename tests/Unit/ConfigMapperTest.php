<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Tests\Unit;

use Tradernet\Laravel\Config\ConfigMapper;
use Tradernet\Laravel\Exceptions\ConnectionNotConfiguredException;
use Tradernet\Laravel\Tests\TestCase;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Exception\ConfigurationException;

final class ConfigMapperTest extends TestCase
{
    private ConfigMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ConfigMapper($this->laravel());
    }

    public function testDefaultsUserAgent(): void
    {
        [, $config] = $this->mapper->map('main', [
            'api_key' => 'pk',
            'api_secret' => 'sk',
        ]);

        self::assertSame(ClientConfig::DEFAULT_USER_AGENT, $config->userAgent);
        self::assertSame(AuthMode::SID_LAZY, $config->authMode);
    }

    public function testMapsFullConnectionConfig(): void
    {
        [$credentials, $config] = $this->mapper->map('main', [
            'api_key' => 'pk',
            'api_secret' => 'sk',
            'login' => 'user@example.com',
            'password' => 'secret',
            'domain' => 'https://tradernet.com',
            'lang' => 'ru',
            'auth_mode' => 'sid_eager',
            'timeout' => 12.5,
            'user_agent' => 'custom-ua/1',
            'sid_cookie' => 'SIDBETA',
            'sid_ttl' => 3600,
            'reauth' => [
                'max_attempts' => 5,
                'window_seconds' => 600,
                'open_seconds' => 1200,
            ],
        ]);

        self::assertSame('pk', $credentials->apiKey());
        self::assertTrue($credentials->hasLoginPassword());
        self::assertSame(AuthMode::SID_EAGER, $config->authMode);
        self::assertSame('ru', $config->lang);
        self::assertSame(12.5, $config->timeout);
        self::assertSame('custom-ua/1', $config->userAgent);
        self::assertSame('SIDBETA', $config->sidCookieName);
        self::assertSame(3600, $config->sidTtlSeconds);
        self::assertSame(5, $config->reauthMaxAttempts);
        self::assertSame(600, $config->reauthWindowSeconds);
        self::assertSame(1200, $config->reauthOpenSeconds);
    }

    public function testPasswordResolverFromContainer(): void
    {
        $this->laravel()->instance('test.password.resolver', new class {
            public function __invoke(): string
            {
                return 'from-vault';
            }
        });

        [$credentials] = $this->mapper->map('main', [
            'api_key' => 'pk',
            'api_secret' => 'sk',
            'login' => 'user@example.com',
            'password_resolver' => 'test.password.resolver',
        ]);

        self::assertSame('from-vault', $credentials->password());
    }

    /**
     * The resolver must stay untouched until the SDK actually needs a SID, so a
     * secrets manager is not contacted on every client construction.
     */
    public function testPasswordResolverIsLazyAndMemoised(): void
    {
        $spy = new class {
            public int $calls = 0;

            public function __invoke(): string
            {
                ++$this->calls;

                return 'from-vault';
            }
        };

        $this->laravel()->instance('test.lazy.resolver', $spy);

        [$credentials] = $this->mapper->map('main', [
            'api_key' => 'pk',
            'api_secret' => 'sk',
            'login' => 'user@example.com',
            'password_resolver' => 'test.lazy.resolver',
        ]);

        self::assertSame(0, $spy->calls);

        self::assertSame('from-vault', $credentials->password());
        self::assertSame('from-vault', $credentials->password());
        self::assertSame(1, $spy->calls);
    }

    public function testRejectsInvalidAuthMode(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->mapper->map('main', [
            'api_key' => 'pk',
            'api_secret' => 'sk',
            'auth_mode' => 'nope',
        ]);
    }

    public function testRequiresCredentials(): void
    {
        $this->expectException(ConnectionNotConfiguredException::class);
        $this->mapper->map('main', ['api_key' => '', 'api_secret' => '']);
    }
}
