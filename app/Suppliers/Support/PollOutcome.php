<?php

namespace App\Suppliers\Support;

enum PollOutcome: string
{
    case SKIPPED = 'skipped';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
}
