<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Release;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

final class ScheduleRelease
{
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
            if ($release->ends_at !== null && ! $release->ends_at->greaterThan($release->starts_at)) {
                throw new ReleaseOperationException('The release end time must be after its start time.');
            }
            if ($release->releaseArtworks->isEmpty()) {
                throw new ReleaseOperationException('Add at least one artwork before scheduling a release.');
            }
            if ($release->events->isEmpty()) {
                throw new ReleaseOperationException('Add at least one timeline event before scheduling a release.');
            }

            $artworkIds = $release->releaseArtworks->pluck('artwork_id');
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

            $release->update(['status' => ReleaseStatus::Scheduled]);

            return $release->fresh();
        });
    }
}
