<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Tests\Unit;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Config;
use Tradernet\Laravel\Session\CacheSessionStorage;
use Tradernet\Laravel\Tests\TestCase;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Exception\ConfigurationException;

final class CacheSessionStorageTest extends TestCase
{
    private string $key;

    private CacheSessionStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = $this->cacheStore('file');
        $cache->clear();

        $this->storage = new CacheSessionStorage(
            cache: $cache,
            encrypter: null,
            prefix: 'tradernet:test:',
            encrypt: false,
        );

        $this->key = hash('sha256', 'https://tradernet.com|user@example.com');
    }

    public function testCorruptPayloadReturnsNull(): void
    {
        $this->cacheStore('file')->put('tradernet:test:' . $this->key, 'not-json', 60);

        self::assertNull($this->storage->load($this->key));
    }

    public function testMetaIsIndependentOfSession(): void
    {
        $this->storage->saveMeta($this->key, ['attempts' => 2, 'opened_at' => null]);
        self::assertSame(['attempts' => 2, 'opened_at' => null], $this->storage->loadMeta($this->key));

        $this->storage->save($this->key, $this->makeSession('abc'));
        $this->storage->delete($this->key);

        self::assertNull($this->storage->load($this->key));
        self::assertSame(['attempts' => 2, 'opened_at' => null], $this->storage->loadMeta($this->key));
    }

    public function testNestedLockDoesNotDeadlock(): void
    {
        $outer = $this->storage->lock($this->key);
        $inner = $this->storage->lock($this->key);

        $this->storage->saveMeta($this->key, ['nested' => true]);
        $inner->unlock();
        $outer->unlock();

        self::assertSame(['nested' => true], $this->storage->loadMeta($this->key));
    }

    public function testRefusesToPersistExpiredSession(): void
    {
        $expired = new Session(
            sid: 'stale-sid',
            sidName: 'SID',
            userId: 42,
            login: 'user@example.com',
            createdAt: new DateTimeImmutable('-30 days'),
            expiresAt: new DateTimeImmutable('-1 hour'),
            domain: 'https://tradernet.com',
        );

        $this->storage->save($this->key, $expired);

        self::assertNull($this->storage->load($this->key));
    }

    /**
     * ArrayStore implements LockProvider, so an instanceof check alone lets it
     * through and ReauthGuard silently stops rate-limiting authByLogin.
     */
    public function testRejectsArrayCacheStore(): void
    {
        Config::set('cache.stores.probe_array', ['driver' => 'array']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('does not persist across processes');

        new CacheSessionStorage(
            cache: $this->cacheStore('probe_array'),
            encrypter: null,
            encrypt: false,
        );
    }

    /**
     * NullStore discards every write, so the reauth circuit breaker would never
     * close and authByLogin attempts would be unbounded.
     */
    public function testRejectsNullCacheStore(): void
    {
        Config::set('cache.stores.probe_null', ['driver' => 'null']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('does not persist across processes');

        new CacheSessionStorage(
            cache: $this->cacheStore('probe_null'),
            encrypter: null,
            encrypt: false,
        );
    }

    public function testRejectsUnsafeKey(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->storage->load('not-a-sha256-key');
    }

    public function testSaveAndLoadRoundtrip(): void
    {
        $session = $this->makeSession('sid-secret-value');

        $this->storage->save($this->key, $session);
        $loaded = $this->storage->load($this->key);

        self::assertNotNull($loaded);
        self::assertSame('sid-secret-value', $loaded->sid);
        self::assertSame('user@example.com', $loaded->login);
    }

    private function cacheStore(string $name): Repository
    {
        /** @var CacheFactory $factory */
        $factory = $this->laravel()->make('cache');

        /** @var Repository $store */
        $store = $factory->store($name);

        return $store;
    }

    private function makeSession(string $sid): Session
    {
        // Relative, not a hardcoded date: save() refuses expired sessions.
        $now = new DateTimeImmutable('now');

        return new Session(
            sid: $sid,
            sidName: 'SID',
            userId: 42,
            login: 'user@example.com',
            createdAt: $now,
            expiresAt: $now->modify('+14 days'),
            domain: 'https://tradernet.com',
        );
    }
}
