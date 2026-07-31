<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Auction;
use App\Models\Release;
use App\Modules\Auction\Application\AuctionSettings;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

final readonly class ScheduleRelease
{
    public function __construct(private AuctionSettings $auctionSettings) {}

    public function handle(int $releaseId): Release
    {
        return DB::transaction(function () use ($releaseId): Release {
            $release = Release::query()
                ->with(['releaseArtworks', 'events'])
                ->lockForUpdate()
                ->findOrFail($releaseId);

            if (! $release->status->canTransitionTo(ReleaseStatus::Scheduled)) {
                throw new ReleaseOperationException('Only a draft release can be scheduled.');
            }
            if ($release->starts_at === null) {
                throw new ReleaseOperationException('A release start time is required.');
            }
            if ($release->auction_starts_at === null || $release->auction_starts_at->lessThan($release->starts_at)) {
                throw new ReleaseOperationException('The common auction start time must be at or after the release start time.');
            }
            if ($release->ends_at === null || ! $release->ends_at->greaterThan($release->auction_starts_at)) {
                throw new ReleaseOperationException('The auction end time must be after the common auction start time.');
            }
            if ($release->releaseArtworks->isEmpty()) {
                throw new ReleaseOperationException('Add at least one artwork before scheduling a release.');
            }
            $artworkIds = $release->releaseArtworks->pluck('artwork_id');
            foreach ($release->releaseArtworks as $releaseArtwork) {
                if ($releaseArtwork->start_price_cents === null || $releaseArtwork->start_price_cents < 1
                    || $releaseArtwork->bid_increment_cents === null || $releaseArtwork->bid_increment_cents < 1) {
                    throw new ReleaseOperationException('Every lot must have a positive start price and bid increment.');
                }
            }
            foreach ($release->events as $event) {
                if ($event->scheduled_at->lessThan($release->starts_at)) {
                    throw new ReleaseOperationException('Every event must be scheduled at or after the release start time.');
                }
                if ($release->ends_at !== null && $event->scheduled_at->greaterThan($release->ends_at)) {
                    throw new ReleaseOperationException('Every event must be scheduled before the release end time.');
                }
                if (in_array($event->type, [ReleaseEventType::DeliverArtwork, ReleaseEventType::DeliverExplanation, ReleaseEventType::DeleteArtworkMessage], true)
                    && ($event->artwork_id === null || ! $artworkIds->contains($event->artwork_id))) {
                    throw new ReleaseOperationException('Artwork events must reference an artwork included in this release.');
                }
                if ($event->type === ReleaseEventType::DeliverExplanation
                    && (! is_array($event->payload) || ! is_string($event->payload['text'] ?? null) || $event->payload['text'] === '')) {
                    throw new ReleaseOperationException('An explanation event must contain text.');
                }
                if ($event->type === ReleaseEventType::ActivateAuction && $event->auction_id === null) {
                    throw new ReleaseOperationException('An auction activation event must reference an auction.');
                }
            }

            foreach ($release->releaseArtworks as $releaseArtwork) {
                if ($releaseArtwork->auction_id !== null) {
                    continue;
                }

                $auction = Auction::query()->create([
                    'artwork_id' => $releaseArtwork->artwork_id,
                    'status' => AuctionStatus::Scheduled,
                    'start_price_cents' => $releaseArtwork->start_price_cents,
                    'bid_increment_cents' => $releaseArtwork->bid_increment_cents,
                    'starts_at' => $release->auction_starts_at,
                    'ends_at' => $release->ends_at,
                    'extension_threshold_seconds' => $this->auctionSettings->integer('auction.anti_sniping_threshold_seconds'),
                    'extension_duration_seconds' => $this->auctionSettings->integer('auction.anti_sniping_extension_seconds'),
                ]);

                $releaseArtwork->update(['auction_id' => $auction->id]);
            }

            $release->update(['status' => ReleaseStatus::Scheduled]);

            return $release->fresh();
        });
    }
}
