<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Support\Facades\DB;

final readonly class CompleteAuction
{
    public function handle(int $auctionId): Auction
    {
        return DB::transaction(function () use ($auctionId): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);

            if (! $auction->status->canTransitionTo(AuctionStatus::Completed)) {
                throw new AuctionOperationException('Auction cannot be completed from its current state.');
            }

            $auction->update(['status' => AuctionStatus::Completed]);

            return $auction->refresh();
        });
    }
}
