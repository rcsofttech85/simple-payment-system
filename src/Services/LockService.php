<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Lock\LockFactory;

final class LockService
{
    private array $locks = [];

    public function __construct(
        private readonly LockFactory $factory
    ) {
    }

    public function acquireLock(string $key, int $ttl = 10): bool
    {
        // If already acquired in this process, treat as locked
        if (isset($this->locks[$key])) {
            return false;
        }

        $lock = $this->factory->createLock($key, $ttl);

        if (!$lock->acquire()) {
            return false;
        }

        $this->locks[$key] = $lock;
        return true;
    }

    public function releaseLock(string $key): void
    {
        if (!isset($this->locks[$key])) {
            return;
        }

        /** @var LockInterface $lock */
        $lock = $this->locks[$key];
        $lock->release();

        unset($this->locks[$key]);
    }
}
