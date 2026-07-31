<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Auction;
use App\Models\Release;
use App\Models\ReleaseEvent;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventStatus;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

final class CancelRelease
{
    public function handle(int $releaseId): Release
    {
        return DB::transaction(function () use ($releaseId): Release {
            $release = Release::query()->with('releaseArtworks')->lockForUpdate()->findOrFail($releaseId);
            if (! $release->status->canTransitionTo(ReleaseStatus::Cancelled)) {
                throw new ReleaseOperationException('The release cannot be cancelled from its current state.');
            }

            $release->update(['status' => ReleaseStatus::Cancelled]);
            ReleaseEvent::query()
                ->where('release_id', $release->id)
                ->whereIn('status', [ReleaseEventStatus::Pending, ReleaseEventStatus::Processing])
                ->update(['status' => ReleaseEventStatus::Cancelled]);

            Auction::query()
                ->whereIn('id', $release->releaseArtworks->pluck('auction_id')->filter())
                ->where('status', AuctionStatus::Scheduled)
                ->update([
                    'status' => AuctionStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by_admin_id' => $release->created_by_admin_id,
                    'cancellation_reason' => 'Exhibition release cancelled before auction start.',
                ]);

            return $release->fresh();
        });
    }
}
