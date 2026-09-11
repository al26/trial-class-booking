<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
    case Refunded = 'REFUNDED';
}
