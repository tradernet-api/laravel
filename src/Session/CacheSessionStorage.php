<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Session;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use JsonException;
use Throwable;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Auth\SessionLockInterface;
use Tradernet\Sdk\Auth\SessionStorageInterface;
use Tradernet\Sdk\Exception\ConfigurationException;

/**
 * SID session storage backed by a Laravel cache store with atomic locks.
 *
 * Session payload uses {@see Session::toArray()} — never jsonSerialize()
 * (which masks sid as "***"). The SID is encrypted; reauth meta is not, since
 * it holds only attempt counters and timestamps.
 *
 * Session and meta live under separate cache keys, which keeps save() and
 * saveMeta() lock-free. lock() is re-entrant per key as a defensive measure so
 * a future nested acquire cannot deadlock against itself.
 */
final class CacheSessionStorage implements SessionStorageInterface
{
    /** @var array<string, int> */
    private array $depth = [];

    /** @var array<string, Lock> */
    private array $locks = [];

    private readonly LockProvider $lockProvider;

    public function __construct(
        private readonly Repository $cache,
        private readonly ?StringEncrypter $encrypter,
        private readonly string $prefix = 'tradernet:sid:',
        private readonly int $lockTtl = 90,
        private readonly int $lockWait = 20,
        private readonly int $metaTtl = 3600,
        private readonly bool $encrypt = true,
    ) {
        $store = $this->cache->getStore();

        // ArrayStore and NullStore both implement LockProvider, so the
        // instanceof check below cannot reject them on its own. Without
        // cross-process persistence ReauthGuard silently stops rate-limiting
        // authByLogin, which risks locking the cabinet out.
        if ($store instanceof ArrayStore || $store instanceof NullStore) {
            throw new ConfigurationException(sprintf(
                'Tradernet session cache store [%s] does not persist across processes, '
                . 'so ReauthGuard cannot rate-limit authByLogin. Use redis, memcached, '
                . 'database, dynamodb or file — or session.driver=memory for tests.',
                $store::class,
            ));
        }

        if (!$store instanceof LockProvider) {
            throw new ConfigurationException(
                'Tradernet session cache store must support atomic locks '
                . '(redis, memcached, database, dynamodb, or file).',
            );
        }

        $this->lockProvider = $store;

        if ($this->encrypt && $this->encrypter === null) {
            throw new ConfigurationException(
                'Session encryption is enabled but no Encrypter is bound.',
            );
        }
    }

    public function delete(string $key): void
    {
        $this->assertSafeKey($key);
        $this->cache->forget($this->sessionKey($key));
    }

    public function load(string $key): ?Session
    {
        $this->assertSafeKey($key);
        $raw = $this->cache->get($this->sessionKey($key));
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->decrypt($raw), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                $this->cache->forget($this->sessionKey($key));

                return null;
            }

            /** @var array<string, mixed> $decoded */
            return Session::fromArray($decoded);
        } catch (Throwable) {
            $this->cache->forget($this->sessionKey($key));

            return null;
        }
    }

    public function loadMeta(string $key): ?array
    {
        $this->assertSafeKey($key);
        $meta = $this->cache->get($this->metaKey($key));
        if (!is_array($meta)) {
            return null;
        }

        /** @var array<string, mixed> $meta */
        return $meta;
    }

    public function lock(string $key): SessionLockInterface
    {
        $this->assertSafeKey($key);

        if (isset($this->depth[$key])) {
            ++$this->depth[$key];

            return new CacheSessionLock(function () use ($key): void {
                $this->releaseNested($key);
            });
        }

        $lock = $this->lockProvider->lock($this->lockKey($key), $this->lockTtl);

        try {
            $lock->block($this->lockWait);
        } catch (LockTimeoutException $e) {
            throw new ConfigurationException(
                sprintf('Unable to acquire Tradernet re-auth lock within %ds.', $this->lockWait),
                0,
                $e,
            );
        }

        $this->depth[$key] = 1;
        $this->locks[$key] = $lock;

        return new CacheSessionLock(function () use ($key): void {
            $this->releaseNested($key);
        });
    }

    public function save(string $key, Session $session): void
    {
        $this->assertSafeKey($key);

        // Refuse to persist an already-expired SID: load() would drop it on the
        // next read anyway, and a short TTL would only mask the real problem.
        $ttl = $session->expiresAt->getTimestamp() - time();
        if ($ttl <= 0) {
            $this->cache->forget($this->sessionKey($key));

            return;
        }

        try {
            $payload = json_encode($session->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConfigurationException('Unable to encode Tradernet session', 0, $e);
        }

        $this->cache->put($this->sessionKey($key), $this->encrypt($payload), $ttl);
    }

    public function saveMeta(string $key, array $meta): void
    {
        $this->assertSafeKey($key);
        $this->cache->put($this->metaKey($key), $meta, $this->metaTtl);
    }

    private function assertSafeKey(string $key): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1) {
            throw new ConfigurationException('Invalid session storage key');
        }
    }

    private function decrypt(string $payload): string
    {
        if (!$this->encrypt || $this->encrypter === null) {
            return $payload;
        }

        try {
            return $this->encrypter->decryptString($payload);
        } catch (DecryptException $e) {
            throw new ConfigurationException('Unable to decrypt Tradernet session', 0, $e);
        }
    }

    private function encrypt(string $payload): string
    {
        if (!$this->encrypt || $this->encrypter === null) {
            return $payload;
        }

        return $this->encrypter->encryptString($payload);
    }

    private function lockKey(string $key): string
    {
        return $this->prefix . 'lock:' . $key;
    }

    private function metaKey(string $key): string
    {
        return $this->prefix . 'meta:' . $key;
    }

    private function releaseNested(string $key): void
    {
        if (!isset($this->depth[$key])) {
            return;
        }

        --$this->depth[$key];
        if ($this->depth[$key] > 0) {
            return;
        }

        unset($this->depth[$key]);
        if (isset($this->locks[$key])) {
            $this->locks[$key]->release();
            unset($this->locks[$key]);
        }
    }

    private function sessionKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
