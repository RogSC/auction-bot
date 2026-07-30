<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Release;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

final class StartRelease
{
    public function handle(int $releaseId, bool $startNow = false): Release
    {
        return DB::transaction(function () use ($releaseId, $startNow): Release {
            $release = Release::query()->lockForUpdate()->findOrFail($releaseId);
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

            return $release->fresh();
        });
    }
}
