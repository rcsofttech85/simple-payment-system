<?php

declare(strict_types=1);

namespace App\ApiResource\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

final class TransferRead
{
    public function __construct(
        #[Groups(["transfer:read"])]
        public string $id,
        #[Groups(["transfer:read"])]
        public string $fromAccountId,
        #[Groups(["transfer:read"])]
        public string $toAccountId,
        #[Groups(["transfer:read"])]
        public string $amount,
        #[Groups(["transfer:read"])]
        public string $currency,
        #[Groups(["transfer:read"])]
        public string $status,
        #[Groups(["transfer:read"])]
        public \DateTimeImmutable $createdAt
    ) {
    }
}
