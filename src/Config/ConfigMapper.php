<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Config;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Tradernet\Laravel\Exceptions\ConnectionNotConfiguredException;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Support\Cast;

/**
 * Maps Laravel config arrays to SDK Credentials + ClientConfig.
 */
final class ConfigMapper
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @return array{0: Credentials, 1: ClientConfig}
     */
    public function map(string $connectionName, array $config): array
    {
        $apiKey = $this->stringOrNull($config['api_key'] ?? null);
        $apiSecret = $this->stringOrNull($config['api_secret'] ?? null);

        if ($apiKey === null || $apiSecret === null) {
            throw ConnectionNotConfiguredException::missingCredentials($connectionName);
        }

        $login = $this->stringOrNull($config['login'] ?? null);
        $password = $this->resolvePassword($config);

        $credentials = new Credentials(
            apiKey: $apiKey,
            apiSecret: $apiSecret,
            login: $login,
            password: $password,
        );

        /** @var array<string, mixed> $reauth */
        $reauth = is_array($config['reauth'] ?? null) ? $config['reauth'] : [];

        $userAgent = $this->stringOrNull($config['user_agent'] ?? null);

        $clientConfig = new ClientConfig(
            domain: $this->stringOrNull($config['domain'] ?? null) ?? 'https://tradernet.com',
            lang: $this->stringOrNull($config['lang'] ?? null) ?? 'en',
            authMode: $this->authMode($config['auth_mode'] ?? AuthMode::SID_LAZY->value),
            sidTtlSeconds: Cast::int($config['sid_ttl'] ?? null, ClientConfig::DEFAULT_SID_TTL_SECONDS),
            sessionPath: null,
            sidCookieName: $this->stringOrNull($config['sid_cookie'] ?? null) ?? 'SID',
            timeout: $this->float($config['timeout'] ?? null, 30.0),
            reauthMaxAttempts: Cast::int($reauth['max_attempts'] ?? null, 3),
            reauthWindowSeconds: Cast::int($reauth['window_seconds'] ?? null, 900),
            reauthOpenSeconds: Cast::int($reauth['open_seconds'] ?? null, 900),
            userAgent: $userAgent ?? ClientConfig::DEFAULT_USER_AGENT,
        );

        return [$credentials, $clientConfig];
    }

    private function authMode(mixed $value): AuthMode
    {
        if ($value instanceof AuthMode) {
            return $value;
        }

        $raw = is_string($value) ? $value : AuthMode::SID_LAZY->value;

        return AuthMode::tryFrom($raw)
            ?? throw new ConfigurationException(sprintf('Invalid Tradernet auth_mode [%s].', $raw));
    }

    /**
     * Numeric config value as float, falling back when not numeric.
     *
     * Mirrors {@see Cast::int()}, which has no float counterpart.
     */
    private function float(mixed $value, float $default): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Password value, or a provider closure when a resolver is configured.
     *
     * The resolver is wrapped rather than invoked here so a secrets manager is
     * only contacted when the SDK actually needs a SID, not on every client
     * construction. The result is memoised for the life of the client.
     *
     * @param array<string, mixed> $config
     *
     * @return null|(Closure(): string)|string
     */
    private function resolvePassword(array $config): Closure|string|null
    {
        /** @var mixed $resolver */
        $resolver = $config['password_resolver'] ?? null;
        if (is_string($resolver) && $resolver !== '') {
            $cached = null;

            return function () use ($resolver, &$cached): string {
                if (is_string($cached)) {
                    return $cached;
                }

                /** @var mixed $resolved */
                $resolved = $this->app->make($resolver);
                if (!is_callable($resolved)) {
                    throw new ConfigurationException(sprintf(
                        'password_resolver [%s] must be invokable.',
                        $resolver,
                    ));
                }

                /** @var mixed $password */
                $password = $resolved();
                if (!is_string($password) || $password === '') {
                    throw new ConfigurationException('password_resolver returned an empty password.');
                }

                return $cached = $password;
            };
        }

        return $this->stringOrNull($config['password'] ?? null);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
