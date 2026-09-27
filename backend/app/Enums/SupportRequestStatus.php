<?php

namespace App\Enums;

enum SupportRequestStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
