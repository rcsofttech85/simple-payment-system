<?php

namespace App\Enum;

enum TransferStatus: string
{
    case PENDING   = 'pending';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
    case CANCELLED = 'cancelled';
}