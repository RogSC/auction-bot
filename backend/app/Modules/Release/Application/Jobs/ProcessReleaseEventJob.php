<?php

declare(strict_types=1);

namespace App\Modules\Release\Application\Jobs;

use App\Modules\Release\Application\DeliverReleaseEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessReleaseEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(public readonly int $eventId) {}

    public function handle(DeliverReleaseEvent $deliverReleaseEvent): void
    {
        $deliverReleaseEvent->handle($this->eventId);
    }
}
