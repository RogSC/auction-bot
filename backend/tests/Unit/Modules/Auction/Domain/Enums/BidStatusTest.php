<?php

declare(strict_types=1);

use App\Modules\Auction\Domain\Enums\BidStatus;

it('uses database-compatible bid status values', function (): void {
    expect(BidStatus::cases())
        ->toHaveCount(5)
        ->and(BidStatus::Winning->value)->toBe('winning')
        ->and(BidStatus::Disqualified->value)->toBe('disqualified');
});

it('permits recovery of a valid bid after the leading bid is cancelled', function (): void {
    expect(BidStatus::Outbid->canTransitionTo(BidStatus::Active))->toBeTrue()
        ->and(BidStatus::Winning->canTransitionTo(BidStatus::Active))->toBeTrue()
        ->and(BidStatus::Cancelled->canTransitionTo(BidStatus::Active))->toBeFalse();
});

it('excludes cancelled and disqualified bids from ranking', function (): void {
    expect(BidStatus::Active->isValidForRanking())->toBeTrue()
        ->and(BidStatus::Outbid->isValidForRanking())->toBeTrue()
        ->and(BidStatus::Cancelled->isValidForRanking())->toBeFalse()
        ->and(BidStatus::Disqualified->isValidForRanking())->toBeFalse();
});
