<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\AuctionRules;
use App\Modules\Auction\Application\UpdateAuctionRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function createAuctionForRulesTest(): Auction
{
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Admin',
        'email' => 'admin@example.test',
        'password' => 'password',
    ]);
    $artworkId = DB::table('artworks')->insertGetId([
        'title' => 'Artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artworks/preview.jpg',
        'created_by_admin_id' => $adminId,
    ]);

    return Auction::query()->create([
        'artwork_id' => $artworkId,
        'status' => 'scheduled',
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'current_price_cents' => 10_000,
        'starts_at' => CarbonImmutable::parse('2026-08-01 10:00:00 UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-02 10:00:00 UTC'),
        'extension_threshold_seconds' => 120,
        'extension_duration_seconds' => 120,
    ]);
}

function rulesForTest(): AuctionRules
{
    return new AuctionRules(
        startPriceCents: 12_000,
        bidIncrementCents: 1_500,
        startsAt: CarbonImmutable::parse('2026-08-03 10:00:00 UTC'),
        endsAt: CarbonImmutable::parse('2026-08-04 10:00:00 UTC'),
        extensionThresholdSeconds: 180,
        extensionDurationSeconds: 180,
    );
}

it('updates auction rules before the first bid', function (): void {
    $auction = createAuctionForRulesTest();

    $updated = app(UpdateAuctionRules::class)->handle($auction->id, rulesForTest());

    expect($updated->start_price_cents)->toBe(12_000)
        ->and($updated->bid_increment_cents)->toBe(1_500)
        ->and($updated->current_price_cents)->toBe(12_000)
        ->and($updated->extension_duration_seconds)->toBe(180);
});

it('rejects auction rule changes after the first bid', function (): void {
    $auction = createAuctionForRulesTest();
    $userId = DB::table('users')->insertGetId([
        'telegram_id' => 100_001,
        'bidder_code' => 'BIDDER-100001',
        'accepted_terms_version' => '1',
        'accepted_terms_at' => now(),
    ]);
    DB::table('bids')->insert([
        'auction_id' => $auction->id,
        'user_id' => $userId,
        'amount_cents' => 11_000,
        'status' => 'active',
        'placed_at' => now(),
    ]);

    app(UpdateAuctionRules::class)->handle($auction->id, rulesForTest());
})->throws(AuctionOperationException::class, 'Auction rules cannot be changed after the first bid.');
