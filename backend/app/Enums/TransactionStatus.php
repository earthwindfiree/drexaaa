<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Completed = 'completed';
    case Pending = 'pending';
    case Rejected = 'rejected';
}
