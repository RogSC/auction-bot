<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Listeners;

use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Telegram\Application\SendReleaseLotsOnAuctionStart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendReleaseLotsOnAuctionStarted implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private SendReleaseLotsOnAuctionStart $sendReleaseLotsOnAuctionStart) {}

    public function handle(AuctionStarted $event): void
    {
        $this->sendReleaseLotsOnAuctionStart->handle($event->auction->id);
    }
}
