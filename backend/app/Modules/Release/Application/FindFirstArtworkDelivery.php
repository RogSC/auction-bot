<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\ReleaseEvent;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Carbon\CarbonImmutable;

final class FindFirstArtworkDelivery
{
    public function handle(): ?CarbonImmutable
    {
        return ReleaseEvent::query()
            ->where('type', ReleaseEventType::DeliverArtwork)
            ->where('scheduled_at', '>=', now())
            ->whereHas('release', fn ($query) => $query->whereIn('status', [ReleaseStatus::Scheduled, ReleaseStatus::Running]))
            ->orderBy('scheduled_at')
            ->first(['scheduled_at'])
            ?->scheduled_at;
    }
}
