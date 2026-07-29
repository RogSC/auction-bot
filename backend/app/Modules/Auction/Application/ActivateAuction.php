<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Modules\Auction\Application\Jobs\FinalizeAuctionJob;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use Illuminate\Support\Facades\DB;

final readonly class ActivateAuction
{
    public function handle(int $auctionId): Auction
    {
        return DB::transaction(function () use ($auctionId): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);

            if (! $auction->status->canTransitionTo(AuctionStatus::Active)) {
                throw new AuctionOperationException('Auction cannot be activated from its current state.');
            }

            if ($auction->starts_at->isFuture()) {
                throw new AuctionOperationException('Auction cannot be activated before its start time.');
            }

            $auction->update(['status' => AuctionStatus::Active]);
            FinalizeAuctionJob::dispatch($auction->id)->delay($auction->ends_at);
            $auction->refresh();
            event(new AuctionStarted($auction));

            return $auction;
        });
    }
}
