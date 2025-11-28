<?php

declare(strict_types=1);

namespace App\ApiResource\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

final class BalanceRead
{
    #[Groups(["balance:read"])]
    public string $accountId;

    #[Groups(["balance:read"])]
    public string $available;
}
