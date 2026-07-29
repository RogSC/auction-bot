<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MonitorFailedJobs extends Command
{
    protected $signature = 'queue:monitor-failed';

    protected $description = 'Report failed queue jobs that require operator attention.';

    public function handle(): int
    {
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            Log::warning('Queue has failed jobs that require attention.', ['failed_jobs' => $failedJobs]);
            $this->warn("Failed jobs: {$failedJobs}");
        }

        return self::SUCCESS;
    }
}
