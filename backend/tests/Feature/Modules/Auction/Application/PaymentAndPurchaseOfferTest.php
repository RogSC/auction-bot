<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\ConfirmPayment;
use App\Modules\Auction\Application\OfferPurchaseToSecondBidder;
use App\Modules\Auction\Domain\Events\PaymentConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function createAuctionAwaitingPaymentForTest(): array
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);
    $winnerId = DB::table('users')->insertGetId(['telegram_id' => 400_001, 'bidder_code' => 'BIDDER-400001']);
    $secondBidderId = DB::table('users')->insertGetId(['telegram_id' => 400_002, 'bidder_code' => 'BIDDER-400002']);
    $auction = Auction::query()->create(['artwork_id' => $artworkId, 'status' => 'awaiting_payment', 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 12_000, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(), 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120, 'auction_winner_id' => $winnerId, 'payment_due_at' => now()->addDay()]);
    $winningBidId = DB::table('bids')->insertGetId(['auction_id' => $auction->id, 'user_id' => $winnerId, 'amount_cents' => 12_000, 'status' => 'winning', 'placed_at' => now()->subDay()]);
    $secondBidId = DB::table('bids')->insertGetId(['auction_id' => $auction->id, 'user_id' => $secondBidderId, 'amount_cents' => 11_000, 'status' => 'outbid', 'placed_at' => now()->subDay()]);
    $auction->update(['winning_bid_id' => $winningBidId]);

    return [$auction, $adminId, $winnerId, $secondBidderId, $winningBidId, $secondBidId];
}

it('confirms payment for the auction winner', function (): void {
    Event::fake([PaymentConfirmed::class]);
    [$auction, , $winnerId, , $winningBidId] = createAuctionAwaitingPaymentForTest();

    $paid = app(ConfirmPayment::class)->handle($auction->id);

    expect($paid->status->value)->toBe('paid')
        ->and($paid->buyer_id)->toBe($winnerId)
        ->and($paid->accepted_bid_id)->toBe($winningBidId);
    Event::assertDispatched(PaymentConfirmed::class);
});

it('offers the second bidder their own bid amount', function (): void {
    [$auction, $adminId, , $secondBidderId, , $secondBidId] = createAuctionAwaitingPaymentForTest();

    $offer = app(OfferPurchaseToSecondBidder::class)->handle($auction->id, $adminId);

    expect($offer->offered_to_user_id)->toBe($secondBidderId)
        ->and($offer->bid_id)->toBe($secondBidId)
        ->and($offer->amount_cents)->toBe(11_000)
        ->and($auction->fresh()->buyer_id)->toBe($secondBidderId)
        ->and($auction->fresh()->accepted_bid_id)->toBe($secondBidId);
});
