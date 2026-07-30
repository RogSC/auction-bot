<?php

declare(strict_types=1);

namespace App\Modules\Release\Application\Commands;

use App\Models\ReleaseEvent;
use App\Modules\Release\Application\Jobs\ProcessReleaseEventJob;
use App\Modules\Release\Domain\Enums\ReleaseEventStatus;
use Illuminate\Console\Command;

final class DispatchDueReleaseEvents extends Command
{
    protected $signature = 'releases:dispatch-due-events';

    protected $description = 'Queue due exhibition release events.';

    public function handle(): int
    {
        ReleaseEvent::query()
            ->where('scheduled_at', '<=', now())
            ->whereHas('release', fn ($query) => $query->where('status', \App\Modules\Release\Domain\Enums\ReleaseStatus::Running))
            ->where(function ($query): void {
                $query->where('status', ReleaseEventStatus::Pending)
                    ->orWhere(function ($query): void {
                        $query->where('status', ReleaseEventStatus::Processing)
                            ->where('updated_at', '<', now()->subMinutes(5));
                    });
            })
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $eventId) => ProcessReleaseEventJob::dispatch($eventId));

        return self::SUCCESS;
    }
}
