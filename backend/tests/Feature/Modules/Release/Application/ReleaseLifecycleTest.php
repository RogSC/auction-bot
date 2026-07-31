<?php

declare(strict_types=1);

use App\Models\Artwork;
use App\Models\Auction;
use App\Models\Release;
use App\Models\ReleaseEvent;
use App\Modules\Release\Application\CancelRelease;
use App\Modules\Release\Application\ReleaseOperationException;
use App\Modules\Release\Application\ScheduleRelease;
use App\Modules\Release\Application\StartRelease;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

function createDraftReleaseForLifecycleTest(): array
{
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Release admin',
        'email' => 'lifecycle-admin'.random_int(1, 999_999).'@example.test',
        'password' => 'password',
    ]);
    $release = Release::query()->create([
        'title' => 'Lifecycle release',
        'status' => ReleaseStatus::Draft,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'created_by_admin_id' => $adminId,
    ]);
    $artwork = Artwork::query()->create([
        'title' => 'Artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/example.jpg',
        'created_by_admin_id' => $adminId,
    ]);

    return [$release, $artwork];
}

it('schedules a valid draft release', function (): void {
    [$release, $artwork] = createDraftReleaseForLifecycleTest();
    $release->releaseArtworks()->create([
        'artwork_id' => $artwork->id,
        'position' => 1,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
    ]);
    ReleaseEvent::query()->create([
        'release_id' => $release->id,
        'artwork_id' => $artwork->id,
        'sequence' => 1,
        'type' => ReleaseEventType::DeliverArtwork,
        'scheduled_at' => $release->starts_at,
    ]);

    app(ScheduleRelease::class)->handle($release->id);

    $releaseArtwork = $release->releaseArtworks()->firstOrFail();

    expect($release->fresh()->status)->toBe(ReleaseStatus::Scheduled)
        ->and($releaseArtwork->auction_id)->not->toBeNull()
        ->and(Auction::query()->findOrFail($releaseArtwork->auction_id))
        ->status->toBe(AuctionStatus::Scheduled)
        ->starts_at->toEqual($release->starts_at)
        ->ends_at->toEqual($release->ends_at);
});

it('creates and starts every release lot at the shared release time', function (): void {
    [$release, $firstArtwork] = createDraftReleaseForLifecycleTest();
    $secondArtwork = Artwork::query()->create([
        'title' => 'Second artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/second.jpg',
        'created_by_admin_id' => $release->created_by_admin_id,
    ]);
    foreach ([$firstArtwork, $secondArtwork] as $position => $artwork) {
        $release->releaseArtworks()->create([
            'artwork_id' => $artwork->id,
            'position' => $position + 1,
            'start_price_cents' => 10_000 + $position * 1_000,
            'bid_increment_cents' => 1_000,
        ]);
    }

    app(ScheduleRelease::class)->handle($release->id);
    $release->update(['starts_at' => now()->subSecond()]);
    Auction::query()->whereIn('id', $release->releaseArtworks()->pluck('auction_id'))->update(['starts_at' => now()->subSecond()]);

    app(StartRelease::class)->handle($release->id);

    expect(Auction::query()->whereIn('id', $release->releaseArtworks()->pluck('auction_id'))
        ->where('status', AuctionStatus::Active)->count())->toBe(2);
});

it('rejects an artwork event for a work not included in the release catalog', function (): void {
    [$release] = createDraftReleaseForLifecycleTest();
    $adminId = $release->created_by_admin_id;
    $otherArtwork = Artwork::query()->create([
        'title' => 'Other artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/other.jpg',
        'created_by_admin_id' => $adminId,
    ]);
    $release->releaseArtworks()->create([
        'artwork_id' => $otherArtwork->id,
        'position' => 1,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
    ]);
    $unlistedArtwork = Artwork::query()->create([
        'title' => 'Unlisted artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/unlisted.jpg',
        'created_by_admin_id' => $adminId,
    ]);
    ReleaseEvent::query()->create([
        'release_id' => $release->id,
        'artwork_id' => $unlistedArtwork->id,
        'sequence' => 1,
        'type' => ReleaseEventType::DeliverArtwork,
        'scheduled_at' => $release->starts_at,
    ]);

    app(ScheduleRelease::class)->handle($release->id);
})->throws(ReleaseOperationException::class, 'Artwork events must reference an artwork included in this release.');

it('cancels pending timeline events together with the release', function (): void {
    [$release, $artwork] = createDraftReleaseForLifecycleTest();
    $release->update(['status' => ReleaseStatus::Scheduled]);
    $event = ReleaseEvent::query()->create([
        'release_id' => $release->id,
        'artwork_id' => $artwork->id,
        'sequence' => 1,
        'type' => ReleaseEventType::DeliverArtwork,
        'scheduled_at' => now()->addHour(),
    ]);

    app(CancelRelease::class)->handle($release->id);

    expect($release->fresh()->status)->toBe(ReleaseStatus::Cancelled)
        ->and($event->fresh()->status)->toBe(ReleaseEventStatus::Cancelled);
});
