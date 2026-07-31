<?php

declare(strict_types=1);

use App\Models\Artwork;
use App\Models\Auction;
use App\Models\Release;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

function createDemoAuctionForResetTest(): array
{
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Admin',
        'email' => 'reset-demo-admin'.random_int(1, 999_999).'@example.test',
        'password' => 'password',
    ]);
    $artwork = Artwork::query()->create([
        'title' => 'Artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/reset.jpg',
        'created_by_admin_id' => $adminId,
    ]);
    $auction = Auction::query()->create([
        'artwork_id' => $artwork->id,
        'status' => AuctionStatus::Scheduled,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'current_price_cents' => 10_000,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'extension_threshold_seconds' => 120,
        'extension_duration_seconds' => 120,
    ]);
    $release = Release::query()->create([
        'title' => 'Release',
        'status' => ReleaseStatus::Scheduled,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'created_by_admin_id' => $adminId,
    ]);
    $release->releaseArtworks()->create([
        'artwork_id' => $artwork->id,
        'position' => 1,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'auction_id' => $auction->id,
    ]);

    return [$release, $auction, $artwork];
}

it('only previews the reset without the force option', function (): void {
    [$release, $auction] = createDemoAuctionForResetTest();

    $this->artisan('demo:reset-auctions')
        ->expectsOutputToContain('Dry run only')
        ->assertExitCode(0);

    expect(Release::query()->find($release->id))->not->toBeNull()
        ->and(Auction::query()->find($auction->id))->not->toBeNull();
});

it('removes releases and auctions but retains artworks when forced', function (): void {
    [$release, $auction, $artwork] = createDemoAuctionForResetTest();

    $this->artisan('demo:reset-auctions --force')->assertExitCode(0);

    expect(Release::query()->find($release->id))->toBeNull()
        ->and(Auction::query()->find($auction->id))->toBeNull()
        ->and(Artwork::query()->find($artwork->id))->not->toBeNull();
});
