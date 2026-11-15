<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';
}
