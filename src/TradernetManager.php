<?php

declare(strict_types=1);

namespace Tradernet\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tradernet\Laravel\Config\ConfigMapper;
use Tradernet\Laravel\Exceptions\ConnectionNotConfiguredException;
use Tradernet\Laravel\Session\SessionStorageFactory;
use Tradernet\Sdk\Tradernet;

/**
 * Resolves named Tradernet SDK clients from config/tradernet.php.
 *
 * This is the only place clients are cached: the container binding for
 * {@see Tradernet} is a plain bind(), so purge() resets DI and facade alike.
 *
 * @mixin Tradernet
 */
final class TradernetManager
{
    /** @var array<string, Tradernet> */
    private array $connections = [];

    public function __construct(
        private readonly ConfigMapper $mapper,
        private readonly SessionStorageFactory $sessionStorageFactory,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Forward any SDK call to the default connection.
     *
     * @param list<mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }

    public function connection(?string $name = null): Tradernet
    {
        $name ??= $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    public function getDefaultConnection(): string
    {
        /** @var mixed $default */
        $default = $this->config->get('tradernet.default', 'main');

        return is_string($default) && $default !== '' ? $default : 'main';
    }

    /**
     * Forget a cached client (useful in tests / Octane after config reload).
     *
     * The next resolve of Tradernet from the container also rebuilds, because
     * that binding delegates here instead of caching on its own.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];

            return;
        }

        unset($this->connections[$name]);
    }

    private function resolve(string $name): Tradernet
    {
        /** @var mixed $raw */
        $raw = $this->config->get('tradernet.connections.' . $name);
        if (!is_array($raw)) {
            throw ConnectionNotConfiguredException::missing($name);
        }

        /** @var array<string, mixed> $raw */
        [$credentials, $clientConfig] = $this->mapper->map($name, $raw);

        /** @var array<string, mixed> $sessionConfig */
        $sessionConfig = is_array($raw['session'] ?? null) ? $raw['session'] : [];

        $storage = $this->sessionStorageFactory->make($sessionConfig, $clientConfig);

        return new Tradernet(
            credentials: $credentials,
            config: $clientConfig,
            storage: $storage,
        );
    }
}
