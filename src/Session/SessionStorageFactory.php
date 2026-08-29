<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Session;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Foundation\Application;
use Tradernet\Sdk\Auth\SessionStorageInterface;
use Tradernet\Sdk\Auth\Storage\InMemorySessionStorage;
use Tradernet\Sdk\Auth\Storage\NullSessionStorage;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Support\Cast;

/**
 * Builds SessionStorageInterface from connection session config.
 */
final class SessionStorageFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly CacheFactory $cacheFactory,
    ) {}

    /**
     * @param array<string, mixed> $sessionConfig
     */
    public function make(array $sessionConfig, ClientConfig $clientConfig): SessionStorageInterface
    {
        if ($clientConfig->authMode === AuthMode::KEYS_ONLY) {
            return new InMemorySessionStorage();
        }

        $driver = is_string($sessionConfig['driver'] ?? null)
            ? $sessionConfig['driver']
            : 'cache';

        return match ($driver) {
            'cache' => $this->makeCache($sessionConfig),
            'memory' => new InMemorySessionStorage(),
            'null' => new NullSessionStorage(),
            default => throw new ConfigurationException(sprintf(
                'Unsupported Tradernet session driver [%s]. Use cache, memory, or null.',
                $driver,
            )),
        };
    }

    /**
     * @param array<string, mixed> $sessionConfig
     */
    private function makeCache(array $sessionConfig): CacheSessionStorage
    {
        /** @var mixed $storeName */
        $storeName = $sessionConfig['store'] ?? null;
        $storeName = is_string($storeName) && $storeName !== '' ? $storeName : null;

        /** @var CacheRepository $cache */
        $cache = $this->cacheFactory->store($storeName);

        /** @var mixed $prefix */
        $prefix = $sessionConfig['prefix'] ?? null;
        $prefix = is_string($prefix) && $prefix !== '' ? $prefix : 'tradernet:sid:';

        $encrypt = (bool) ($sessionConfig['encrypt'] ?? true);

        // StringEncrypter, not Encrypter: only the string API is used, and the
        // base contract does not declare encryptString()/decryptString().
        $encrypter = $encrypt ? $this->app->make(StringEncrypter::class) : null;

        return new CacheSessionStorage(
            cache: $cache,
            encrypter: $encrypter,
            prefix: $prefix,
            lockTtl: Cast::int($sessionConfig['lock_ttl'] ?? null, 90),
            lockWait: Cast::int($sessionConfig['lock_wait'] ?? null, 20),
            metaTtl: Cast::int($sessionConfig['meta_ttl'] ?? null, 3600),
            encrypt: $encrypt,
        );
    }
}
