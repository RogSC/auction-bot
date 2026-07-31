<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Auction;
use App\Models\Release;
use App\Modules\Auction\Application\ActivateAuction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

final readonly class StartRelease
{
    public function __construct(private ActivateAuction $activateAuction) {}

    public function handle(int $releaseId, bool $startNow = false): Release
    {
        return DB::transaction(function () use ($releaseId, $startNow): Release {
            $release = Release::query()->with('releaseArtworks')->lockForUpdate()->findOrFail($releaseId);
            if ($release->status === ReleaseStatus::Running) {
                return $release;
            }
            if (! $release->status->canTransitionTo(ReleaseStatus::Running)) {
                throw new ReleaseOperationException('Only a scheduled release can be started.');
            }
            if ($release->starts_at === null) {
                throw new ReleaseOperationException('A release start time is required.');
            }
            if ($release->starts_at->isFuture() && ! $startNow) {
                throw new ReleaseOperationException('The release cannot start before its scheduled start time.');
            }

            $attributes = ['status' => ReleaseStatus::Running];
            if ($startNow && $release->starts_at->isFuture()) {
                $attributes['starts_at'] = now();
            }
            $release->update($attributes);

            $auctionIds = $release->releaseArtworks->pluck('auction_id')->filter()->all();
            if (count($auctionIds) !== $release->releaseArtworks->count()) {
                throw new ReleaseOperationException('Every release lot must have a scheduled auction before the release starts.');
            }

            if (isset($attributes['starts_at'])) {
                Auction::query()
                    ->whereIn('id', $auctionIds)
                    ->where('status', AuctionStatus::Scheduled)
                    ->update(['starts_at' => $attributes['starts_at']]);
            }

            foreach ($auctionIds as $auctionId) {
                $this->activateAuction->handle((int) $auctionId);
            }

            return $release->fresh();
        });
    }
}
