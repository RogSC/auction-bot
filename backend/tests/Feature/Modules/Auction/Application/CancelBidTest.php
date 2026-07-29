<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\CancelBid;
use Illuminate\Support\Facades\DB;

function createAuctionForCancellationTest(): array
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);
    $auction = Auction::query()->create(['artwork_id' => $artworkId, 'status' => 'active', 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 12_000, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120]);

    return [$auction, $adminId];
}

it('cancels a bid and restores the next valid leader', function (): void {
    [$auction, $adminId] = createAuctionForCancellationTest();
    $lowerBidderId = DB::table('users')->insertGetId(['telegram_id' => 300_001, 'bidder_code' => 'BIDDER-300001']);
    $leaderId = DB::table('users')->insertGetId(['telegram_id' => 300_002, 'bidder_code' => 'BIDDER-300002']);
    DB::table('bids')->insert(['auction_id' => $auction->id, 'user_id' => $lowerBidderId, 'amount_cents' => 11_000, 'status' => 'outbid', 'placed_at' => now()->subMinute()]);
    $bidId = DB::table('bids')->insertGetId(['auction_id' => $auction->id, 'user_id' => $leaderId, 'amount_cents' => 12_000, 'status' => 'active', 'placed_at' => now()]);

    app(CancelBid::class)->handle($auction->id, $bidId, $adminId, 'Incorrect bid');

    expect($auction->fresh()->current_leader_id)->toBe($lowerBidderId)
        ->and($auction->fresh()->current_price_cents)->toBe(11_000)
        ->and(DB::table('bids')->find($bidId)->status)->toBe('cancelled');
});
