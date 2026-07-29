<?php

declare(strict_types=1);

use App\Modules\Auction\Domain\Enums\AuctionStatus;

it('uses database-compatible auction status values', function (): void {
    expect(AuctionStatus::cases())
        ->toHaveCount(8)
        ->and(AuctionStatus::Active->value)->toBe('active')
        ->and(AuctionStatus::NoSale->value)->toBe('no_sale');
});

it('permits only valid auction state transitions', function (): void {
    expect(AuctionStatus::Draft->canTransitionTo(AuctionStatus::Scheduled))->toBeTrue()
        ->and(AuctionStatus::Active->canTransitionTo(AuctionStatus::AwaitingPayment))->toBeTrue()
        ->and(AuctionStatus::Active->canTransitionTo(AuctionStatus::Completed))->toBeFalse()
        ->and(AuctionStatus::Completed->canTransitionTo(AuctionStatus::Active))->toBeFalse();
});

it('identifies terminal auction states', function (): void {
    expect(AuctionStatus::Completed->isTerminal())->toBeTrue()
        ->and(AuctionStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(AuctionStatus::NoSale->isTerminal())->toBeTrue()
        ->and(AuctionStatus::Paid->isTerminal())->toBeFalse();
});
