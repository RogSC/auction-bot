<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Jobs;

use App\Modules\Telegram\Application\SyncAuctionReplyKeyboard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncAuctionReplyKeyboardJob implements ShouldQueue
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

    public function __construct(public readonly string $eventKey) {}

    public function handle(SyncAuctionReplyKeyboard $syncAuctionReplyKeyboard): void
    {
        $syncAuctionReplyKeyboard->handle($this->eventKey);
    }
}
