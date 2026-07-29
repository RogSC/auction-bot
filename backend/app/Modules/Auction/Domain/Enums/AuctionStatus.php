<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Enums;

enum AuctionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoSale = 'no_sale';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Scheduled, self::Cancelled], true),
            self::Scheduled => in_array($target, [self::Active, self::Cancelled], true),
            self::Active => in_array($target, [self::AwaitingPayment, self::NoSale, self::Cancelled], true),
            self::AwaitingPayment => in_array($target, [self::Paid, self::NoSale, self::Cancelled], true),
            self::Paid => $target === self::Completed,
            self::Completed, self::Cancelled, self::NoSale => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoSale], true);
    }
}
