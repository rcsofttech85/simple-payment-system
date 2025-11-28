<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Cache\CacheItemPoolInterface;

final class IdempotencyService
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function getResponse(string $key): ?array
    {
        $item = $this->cache->getItem("idempotency_$key");

        if (!$item->isHit()) {
            return null;
        }


        return $item->get();
    }

    public function storeResponse(string $key, array $response, int $ttl = 3600): void
    {
        $item = $this->cache->getItem("idempotency_$key");
        $item->set($response);
        $item->expiresAfter($ttl);

        $this->cache->save($item);
    }
}
