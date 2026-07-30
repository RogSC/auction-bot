<?php

declare(strict_types=1);

namespace App\Modules\Release\Application\Commands;

use App\Models\Release;
use App\Modules\Release\Application\StartRelease;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Console\Command;

final class ActivateScheduledReleases extends Command
{
    protected $signature = 'releases:activate-scheduled';

    protected $description = 'Start scheduled exhibition releases whose start time has arrived.';

    public function handle(StartRelease $startRelease): int
    {
        Release::query()
            ->where('status', ReleaseStatus::Scheduled)
            ->where('starts_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $releaseId) => $startRelease->handle($releaseId));

        return self::SUCCESS;
    }
}
