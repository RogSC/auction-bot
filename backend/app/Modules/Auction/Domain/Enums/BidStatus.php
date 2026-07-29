<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Enums;

enum BidStatus: string
{
    case Active = 'active';
    case Outbid = 'outbid';
    case Winning = 'winning';
    case Cancelled = 'cancelled';
    case Disqualified = 'disqualified';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Outbid, self::Winning, self::Cancelled, self::Disqualified], true),
            self::Outbid => in_array($target, [self::Active, self::Cancelled, self::Disqualified], true),
            self::Winning => in_array($target, [self::Active, self::Cancelled, self::Disqualified], true),
            self::Cancelled, self::Disqualified => false,
        };
    }

    public function isValidForRanking(): bool
    {
        return in_array($this, [self::Active, self::Outbid, self::Winning], true);
    }
}
