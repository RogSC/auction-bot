<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\PlaceNextBid;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Domain\Events\BidPlaced;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function createActiveAuctionForBidTest(int $endsInSeconds = 600): Auction
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);

    return Auction::query()->create([
        'artwork_id' => $artworkId,
        'status' => 'active',
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'current_price_cents' => 10_000,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addSeconds($endsInSeconds),
        'extension_threshold_seconds' => 120,
        'extension_duration_seconds' => 120,
        'version' => 1,
    ]);
}

function createBidderForBidTest(bool $acceptedTerms = true): int
{
    return DB::table('users')->insertGetId([
        'telegram_id' => random_int(1_000_000, 9_999_999),
        'bidder_code' => 'BIDDER-'.random_int(100_000, 999_999),
        'accepted_terms_version' => $acceptedTerms ? '1' : null,
        'accepted_terms_at' => $acceptedTerms ? now() : null,
    ]);
}

it('calculates the next bid from the locked auction state and outbids the previous leader', function (): void {
    $auction = createActiveAuctionForBidTest();
    $previousLeaderId = createBidderForBidTest();
    $bidderId = createBidderForBidTest();
    $previousBidId = DB::table('bids')->insertGetId(['auction_id' => $auction->id, 'user_id' => $previousLeaderId, 'amount_cents' => 11_000, 'status' => 'active', 'placed_at' => now()]);
    $auction->update(['current_price_cents' => 11_000, 'current_leader_id' => $previousLeaderId]);

    $bid = app(PlaceNextBid::class)->handle($auction->id, $bidderId, 1);

    expect($bid->amount_cents)->toBe(12_000)
        ->and($bid->user_id)->toBe($bidderId)
        ->and($auction->fresh()->current_price_cents)->toBe(12_000)
        ->and($auction->fresh()->current_leader_id)->toBe($bidderId)
        ->and(DB::table('bids')->find($previousBidId)->status)->toBe('outbid');
});

it('rejects a bid when terms have not been accepted', function (): void {
    $auction = createActiveAuctionForBidTest();

    app(PlaceNextBid::class)->handle($auction->id, createBidderForBidTest(false), 1);
})->throws(AuctionOperationException::class, 'Terms must be accepted before bidding.');

it('rejects a bid from a stale auction view', function (): void {
    $auction = createActiveAuctionForBidTest();

    app(PlaceNextBid::class)->handle($auction->id, createBidderForBidTest(), 0);
})->throws(AuctionOperationException::class, 'Auction view is stale.');

it('extends an auction and emits domain events when a bid arrives near the end', function (): void {
    Event::fake([BidPlaced::class, AuctionExtended::class]);
    $auction = createActiveAuctionForBidTest(60);
    $previousEndsAt = $auction->ends_at;

    app(PlaceNextBid::class)->handle($auction->id, createBidderForBidTest(), 1);

    expect($auction->fresh()->ends_at->greaterThan($previousEndsAt))->toBeTrue();
    Event::assertDispatched(BidPlaced::class);
    Event::assertDispatched(AuctionExtended::class);
});
