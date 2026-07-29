<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\ActivateAuction;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\CompleteAuction;
use App\Modules\Auction\Application\ConfirmPayment;
use App\Modules\Auction\Application\FinalizeAuction;
use App\Modules\Auction\Application\ScheduleAuction;
use Illuminate\Support\Facades\DB;

function createDraftAuctionForLifecycleTest(): Auction
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);

    return Auction::query()->create(['artwork_id' => $artworkId, 'status' => 'draft', 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 10_000, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120]);
}

it('moves a winning auction through the complete lifecycle', function (): void {
    $auction = createDraftAuctionForLifecycleTest();

    expect(app(ScheduleAuction::class)->handle($auction->id)->status->value)->toBe('scheduled')
        ->and(app(ActivateAuction::class)->handle($auction->id)->status->value)->toBe('active');

    $winnerId = DB::table('users')->insertGetId(['telegram_id' => 600_001, 'bidder_code' => 'BIDDER-600001']);
    $winningBidId = DB::table('bids')->insertGetId(['auction_id' => $auction->id, 'user_id' => $winnerId, 'amount_cents' => 11_000, 'status' => 'active', 'placed_at' => now()]);
    $auction->update(['current_price_cents' => 11_000, 'current_leader_id' => $winnerId, 'ends_at' => now()->subSecond()]);

    expect(app(FinalizeAuction::class)->handle($auction->id)->status->value)->toBe('awaiting_payment')
        ->and($auction->fresh()->winning_bid_id)->toBe($winningBidId)
        ->and(app(ConfirmPayment::class)->handle($auction->id)->status->value)->toBe('paid')
        ->and(app(CompleteAuction::class)->handle($auction->id)->status->value)->toBe('completed');
});

it('does not activate an auction before its start time', function (): void {
    $auction = createDraftAuctionForLifecycleTest();
    $auction->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    app(ScheduleAuction::class)->handle($auction->id);

    app(ActivateAuction::class)->handle($auction->id);
})->throws(AuctionOperationException::class, 'Auction cannot be activated before its start time.');
