<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case Confirmed = 'CONFIRMED';
    case PaymentFailed = 'PAYMENT_FAILED';
    case ClassFull = 'CLASS_FULL';
}
