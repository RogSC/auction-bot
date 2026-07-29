<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\PlaceNextBid;
use App\Modules\Auction\Domain\Events\BidPlaced;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

function createActiveAuctionForTransactionTest(): array
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);
    $auction = Auction::query()->create(['artwork_id' => $artworkId, 'status' => 'active', 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 10_000, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120, 'version' => 1]);
    $userId = DB::table('users')->insertGetId(['telegram_id' => 500_001, 'bidder_code' => 'BIDDER-500001', 'accepted_terms_version' => '1', 'accepted_terms_at' => now()]);

    return [$auction, $userId];
}

it('dispatches bid events after a successful transaction commit', function (): void {
    Event::fake([BidPlaced::class]);
    [$auction, $userId] = createActiveAuctionForTransactionTest();

    app(PlaceNextBid::class)->handle($auction->id, $userId, 1);

    Event::assertDispatched(BidPlaced::class, fn (BidPlaced $event): bool => $event->bid->auction_id === $auction->id);
});

it('rolls back bid data and discards after-commit events when an outer transaction fails', function (): void {
    Event::fake([BidPlaced::class]);
    [$auction, $userId] = createActiveAuctionForTransactionTest();

    try {
        DB::transaction(function () use ($auction, $userId): void {
            app(PlaceNextBid::class)->handle($auction->id, $userId, 1);

            throw new RuntimeException('Force rollback.');
        });
    } catch (RuntimeException) {
        // The test verifies the state after the deliberate rollback.
    }

    expect(DB::table('bids')->where('auction_id', $auction->id)->count())->toBe(0)
        ->and($auction->fresh()->current_price_cents)->toBe(10_000)
        ->and($auction->fresh()->current_leader_id)->toBeNull();
    Event::assertNotDispatched(BidPlaced::class);
});
