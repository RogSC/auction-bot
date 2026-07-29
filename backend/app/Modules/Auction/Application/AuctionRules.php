<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use Carbon\CarbonImmutable;

final readonly class AuctionRules
{
    public function __construct(
        public int $startPriceCents,
        public int $bidIncrementCents,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $extensionThresholdSeconds,
        public int $extensionDurationSeconds,
    ) {
        if ($startPriceCents <= 0 || $bidIncrementCents <= 0) {
            throw new AuctionOperationException('Auction prices must be positive.');
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new AuctionOperationException('Auction end time must be after its start time.');
        }

        if ($extensionThresholdSeconds < 0 || $extensionDurationSeconds < 0) {
            throw new AuctionOperationException('Auction extension settings cannot be negative.');
        }
    }
}
