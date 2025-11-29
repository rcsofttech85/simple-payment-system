<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Transfer;
use Symfony\Contracts\EventDispatcher\Event;

final class TransferCompletedEvent extends Event
{
    public const NAME = 'transfer.completed';

    public function __construct(private readonly Transfer $transfer)
    {

    }

    public function getTransfer(): Transfer
    {
        return $this->transfer;
    }
}
