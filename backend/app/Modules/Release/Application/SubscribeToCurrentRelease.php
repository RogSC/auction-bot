<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Release;
use App\Models\ReleaseSubscription;
use App\Models\User;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

final class SubscribeToCurrentRelease
{
    public function handle(User $user): ?ReleaseSubscription
    {
        return DB::transaction(function () use ($user): ?ReleaseSubscription {
            $now = now();

            $release = Release::query()
                ->where('status', ReleaseStatus::Running)
                ->where('starts_at', '<=', $now)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->orderByDesc('starts_at')
                ->lockForUpdate()
                ->first();

            if ($release === null) {
                return null;
            }

            $subscription = ReleaseSubscription::query()
                ->where('release_id', $release->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($subscription === null) {
                return ReleaseSubscription::query()->create([
                    'release_id' => $release->id,
                    'user_id' => $user->id,
                    'subscribed_at' => $now,
                ]);
            }

            if ($subscription->unsubscribed_at !== null) {
                $subscription->update([
                    'subscribed_at' => $now,
                    'unsubscribed_at' => null,
                ]);
            }

            return $subscription;
        });
    }
}
