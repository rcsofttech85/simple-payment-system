<?php

declare(strict_types=1);

namespace App\ApiResource\DTO;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class TransferRequest
{
    #[Assert\NotBlank]
    #[Groups(["transfer:write"])]
    public string $fromAccountId;

    #[Assert\NotBlank]
    #[Groups(["transfer:write"])]
    public string $toAccountId;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(["transfer:write"])]
    public string $amount;

    #[Assert\NotBlank]
    #[Groups(["transfer:write"])]
    public string $currency;
}
