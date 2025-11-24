<?php

namespace App\Enums;

enum VoucherAuditActions: string
{
    case REQUESTED = 'requested';
    case VIEWED = 'viewed';
    case COPIED = 'copied';
}
