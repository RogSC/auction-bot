<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Listeners;

use App\Modules\Auction\Domain\Events\AuctionFinished;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Telegram\Application\SyncAuctionReplyKeyboard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SyncAuctionReplyKeyboardListener implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private SyncAuctionReplyKeyboard $syncAuctionReplyKeyboard) {}

    public function handle(AuctionStarted|AuctionFinished|AuctionCancelled $event): void
    {
        $this->syncAuctionReplyKeyboard->handle(class_basename($event).'-'.$event->auction->id);
    }
}
