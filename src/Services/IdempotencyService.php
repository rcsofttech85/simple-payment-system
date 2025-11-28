<?php

declare(strict_types=1);

namespace App\Services;

use Predis\Client;

final class IdempotencyService
{
    private Client $redis;
    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function getResponse(string $key): ?array
    {
        $data = $this->redis->get("idempotency:$key");
        return $data ? json_decode($data, true) : null;
    }

    public function storeResponse(string $key, array $response, int $ttl = 3600): void
    {
        $this->redis->setex("idempotency:$key", $ttl, json_encode($response));
    }
}
