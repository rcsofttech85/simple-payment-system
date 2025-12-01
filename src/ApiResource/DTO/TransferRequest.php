<?php

declare(strict_types=1);

namespace App\ApiResource\DTO;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class TransferRequest
{
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Uuid(message: "Invalid UUID for fromAccountId"),
            Groups(["transfer:write:v1"])
        ]
        public string $fromAccountId,
        #[
            Assert\NotBlank,
            Assert\Uuid(message: "Invalid UUID for toAccountId"),
            Assert\NotEqualTo(
                propertyPath: "fromAccountId",
                message: "Sender and receiver accounts must be different."
            ),
            Groups(["transfer:write:v1"])
        ]
        public string $toAccountId,
        #[
            Assert\NotBlank,
            Assert\Regex(
                pattern: "/^\d+(\.\d{1,2})?$/",
                message: "Amount must be a valid decimal with up to 2 digits."
            ),
            Assert\Positive,
            Groups(["transfer:write:v1"])
        ]
        public string $amount,
        #[
            Assert\NotBlank,
            Assert\Currency(message: "Invalid or unsupported currency."),
            Groups(["transfer:write:v1"])
        ]
        public string $currency,
        #[
            Assert\NotBlank,
            Assert\Length(
                max: 100,
                maxMessage: "Idempotency key cannot exceed 100 characters."
            ),
            Assert\Regex(
                pattern: "/^[A-Za-z0-9\-_]+$/",
                message: "Idempotency key may only contain letters, numbers, hyphens, underscores, and colons."
            ),
            Groups(["transfer:write:v1"])
        ]
        public $idempotencyKey,
    ) {
    }
}
