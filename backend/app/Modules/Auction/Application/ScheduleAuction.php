<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Support\Facades\DB;

final readonly class ScheduleAuction
{
    public function handle(int $auctionId): Auction
    {
        return DB::transaction(function () use ($auctionId): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);

            if (! $auction->status->canTransitionTo(AuctionStatus::Scheduled)) {
                throw new AuctionOperationException('Auction cannot be scheduled from its current state.');
            }

            $auction->update(['status' => AuctionStatus::Scheduled]);

            return $auction->refresh();
        });
    }
}
