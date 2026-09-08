<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
