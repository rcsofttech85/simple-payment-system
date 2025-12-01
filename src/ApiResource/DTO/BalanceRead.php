<?php

declare(strict_types=1);

namespace App\ApiResource\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\ApiResource\Provider\BalanceProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/v1/accounts/{id}/balance',
            name: 'account_balance',
            provider: BalanceProvider::class,
            output: BalanceRead::class
        )
    ],
)]
final class BalanceRead
{
    public function __construct(
        #[Groups(["balance:read"])]
        public string $accountId,
        #[Groups(["balance:read"])]
        public string $available
    ) {
    }
}
