<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\CancelAuction;
use Illuminate\Support\Facades\DB;

function createAuctionForCancellationLifecycleTest(string $status = 'active'): array
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);
    $auction = Auction::query()->create(['artwork_id' => $artworkId, 'status' => $status, 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 10_000, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120]);

    return [$auction, $adminId];
}

it('cancels an auction with an auditable reason', function (): void {
    [$auction, $adminId] = createAuctionForCancellationLifecycleTest();

    $cancelled = app(CancelAuction::class)->handle($auction->id, $adminId, 'Artwork withdrawn by administrator.');

    expect($cancelled->status->value)->toBe('cancelled')
        ->and($cancelled->cancelled_by_admin_id)->toBe($adminId)
        ->and($cancelled->cancellation_reason)->toBe('Artwork withdrawn by administrator.')
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

it('does not cancel an already completed auction', function (): void {
    [$auction, $adminId] = createAuctionForCancellationLifecycleTest('completed');

    app(CancelAuction::class)->handle($auction->id, $adminId, 'Too late.');
})->throws(AuctionOperationException::class, 'Auction cannot be cancelled from its current state.');
