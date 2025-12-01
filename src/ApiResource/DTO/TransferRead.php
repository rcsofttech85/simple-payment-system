<?php

declare(strict_types=1);

namespace App\ApiResource\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

final class TransferRead
{
    public function __construct(
        #[Groups(["transfer:read:v1"])]
        public string $id,
        #[Groups(["transfer:read:v1"])]
        public string $fromAccountId,
        #[Groups(["transfer:read:v1"])]
        public string $toAccountId,
        #[Groups(["transfer:read:v1"])]
        public string $amount,
        #[Groups(["transfer:read:v1"])]
        public string $currency,
        #[Groups(["transfer:read:v1"])]
        public string $status,
        #[Groups(["transfer:read:v1"])]
        public \DateTimeImmutable $createdAt
    ) {
    }
}
