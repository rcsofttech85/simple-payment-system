<?php

declare(strict_types=1);

namespace App\Services;

use Predis\Client;

final class RedisLockService
{
    private Client $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function acquireLock(string $key, int $ttl = 10): bool
    {
        return $this->redis->set($key, "locked", "NX", "EX", $ttl) !== null;
    }

    public function releaseLock(string $key): void
    {
        $this->redis->del([$key]);
    }
}
