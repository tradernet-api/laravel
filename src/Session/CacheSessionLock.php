<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Session;

use Closure;
use Tradernet\Sdk\Auth\SessionLockInterface;

/**
 * SessionLockInterface adapter around a release callback.
 */
final class CacheSessionLock implements SessionLockInterface
{
    private bool $released = false;

    /**
     * @param Closure(): void $release
     */
    public function __construct(
        private readonly Closure $release,
    ) {}

    public function unlock(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        ($this->release)();
    }
}
