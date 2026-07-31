<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\Commands;

use App\Models\Auction;
use App\Modules\Auction\Application\ActivateAuction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Console\Command;

final class ActivateScheduledAuctions extends Command
{
    protected $signature = 'auctions:activate-scheduled';

    protected $description = 'Start scheduled auctions whose common start time has arrived.';

    public function handle(ActivateAuction $activateAuction): int
    {
        Auction::query()
            ->where('status', AuctionStatus::Scheduled)
            ->where('starts_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $auctionId) => $activateAuction->handle($auctionId));

        return self::SUCCESS;
    }
}
