<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Auction\Application\Jobs\FinalizeAuctionJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function createExpiredAuctionForDispatchTest(): Auction
{
    $adminId = DB::table('admins')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
    $artworkId = DB::table('artworks')->insertGetId(['title' => 'Artwork', 'description' => 'Description', 'preview_disk' => 'local', 'preview_path' => 'preview.jpg', 'created_by_admin_id' => $adminId]);

    return Auction::query()->create(['artwork_id' => $artworkId, 'status' => 'active', 'start_price_cents' => 10_000, 'bid_increment_cents' => 1_000, 'current_price_cents' => 10_000, 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinute(), 'extension_threshold_seconds' => 120, 'extension_duration_seconds' => 120]);
}

it('queues finalization jobs for expired active auctions', function (): void {
    Queue::fake();
    $auction = createExpiredAuctionForDispatchTest();

    $this->artisan('auctions:finalize-expired')->assertSuccessful();

    Queue::assertPushed(FinalizeAuctionJob::class, fn (FinalizeAuctionJob $job): bool => $job->auctionId === $auction->id);
});
