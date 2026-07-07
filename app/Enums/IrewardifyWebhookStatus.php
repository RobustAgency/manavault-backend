<?php

namespace App\Enums;

enum IrewardifyWebhookStatus: string
{
    case ORDERED = 'Ordered';
    case DELIVERED = 'Delivered';
    case FAILED = 'Failed';
}
