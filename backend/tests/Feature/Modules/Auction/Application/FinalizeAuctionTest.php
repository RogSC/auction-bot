<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\FinalizeAuction;
use App\Modules\Auction\Domain\Events\AuctionFinished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function createExpiredAuctionForFinalizationTest(): Auction
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);

    return Auction::query()->create([
        'artwork_id' => $artworkId,
        'status' => 'active',
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'current_price_cents' => 10_000,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subMinute(),
        'extension_threshold_seconds' => 120,
        'extension_duration_seconds' => 120,
    ]);
}

it('marks an expired auction without bids as no sale', function (): void {
    Event::fake([AuctionFinished::class]);
    $auction = createExpiredAuctionForFinalizationTest();

    $finalized = app(FinalizeAuction::class)->handle($auction->id);

    expect($finalized->status->value)->toBe('no_sale');
    Event::assertDispatched(AuctionFinished::class);
});

it('sets the highest valid bidder as winner and creates a payment deadline', function (): void {
    $auction = createExpiredAuctionForFinalizationTest();
    $lowerBidderId = DB::table('users')->insertGetId(['telegram_id' => 200_001, 'bidder_code' => 'BIDDER-200001']);
    $winnerId = DB::table('users')->insertGetId(['telegram_id' => 200_002, 'bidder_code' => 'BIDDER-200002']);
    DB::table('bids')->insert(['auction_id' => $auction->id, 'user_id' => $lowerBidderId, 'amount_cents' => 11_000, 'status' => 'outbid', 'placed_at' => now()->subMinute()]);
    $winningBidId = DB::table('bids')->insertGetId(['auction_id' => $auction->id, 'user_id' => $winnerId, 'amount_cents' => 12_000, 'status' => 'active', 'placed_at' => now()]);

    $finalized = app(FinalizeAuction::class)->handle($auction->id);

    expect($finalized->status->value)->toBe('awaiting_payment')
        ->and($finalized->auction_winner_id)->toBe($winnerId)
        ->and($finalized->winning_bid_id)->toBe($winningBidId)
        ->and($finalized->payment_due_at)->not->toBeNull();
});

it('is idempotent after finalization', function (): void {
    $auction = createExpiredAuctionForFinalizationTest();

    $first = app(FinalizeAuction::class)->handle($auction->id);
    $second = app(FinalizeAuction::class)->handle($auction->id);

    expect($first->status->value)->toBe('no_sale')
        ->and($second->status->value)->toBe('no_sale');
});
