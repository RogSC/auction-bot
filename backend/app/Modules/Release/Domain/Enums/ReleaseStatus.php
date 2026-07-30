<?php

declare(strict_types=1);

namespace App\Modules\Release\Domain\Enums;

enum ReleaseStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Scheduled, self::Cancelled], true),
            self::Scheduled => in_array($target, [self::Running, self::Cancelled], true),
            self::Running => in_array($target, [self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }
}
