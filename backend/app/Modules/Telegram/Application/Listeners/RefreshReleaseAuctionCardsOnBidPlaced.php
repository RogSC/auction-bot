<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Listeners;

use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Telegram\Application\RefreshReleaseAuctionCards;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class RefreshReleaseAuctionCardsOnBidPlaced implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private RefreshReleaseAuctionCards $refreshReleaseAuctionCards) {}

    public function handle(BidPlaced $event): void
    {
        $this->refreshReleaseAuctionCards->handle($event->auction->id);
    }
}
