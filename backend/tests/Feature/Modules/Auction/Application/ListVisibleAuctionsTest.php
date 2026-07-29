<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\ListVisibleAuctions;
use Illuminate\Support\Facades\DB;

function createAuctionForVisibilityTest(string $status, mixed $startsAt, mixed $endsAt): Auction
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin'.random_int(1, 999_999).'@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);

    return Auction::query()->create(['artwork_id' => $artworkId, 'status' => $status, 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 10_000, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120]);
}

it('only lists auctions that are active, started, and not yet ended', function (): void {
    $visible = createAuctionForVisibilityTest('active', now()->subMinute(), now()->addHour());
    createAuctionForVisibilityTest('scheduled', now()->addHour(), now()->addHours(2));
    createAuctionForVisibilityTest('active', now()->addHour(), now()->addHours(2));
    createAuctionForVisibilityTest('active', now()->subHours(2), now()->subHour());

    expect(app(ListVisibleAuctions::class)->handle()->modelKeys())->toBe([$visible->id]);
});
