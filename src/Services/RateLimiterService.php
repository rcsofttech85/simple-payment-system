<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RateLimiterService
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $transferLimiter
    ) {
    }

    public function consumeTransferLimit(string $key): void
    {
        $limiter = $this->transferLimiter->create($key);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()?->getTimestamp() ?? 0,
                'Rate limit exceeded. Try again later.'
            );
        }
    }
}
