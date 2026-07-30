<?php

declare(strict_types=1);

namespace App\Modules\Release\Domain\Enums;

enum ReleaseEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
