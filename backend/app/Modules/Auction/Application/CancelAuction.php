<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use Illuminate\Support\Facades\DB;

final readonly class CancelAuction
{
    public function handle(int $auctionId, int $adminId, string $reason): Auction
    {
        return DB::transaction(function () use ($auctionId, $adminId, $reason): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);

            if (! $auction->status->canTransitionTo(AuctionStatus::Cancelled)) {
                throw new AuctionOperationException('Auction cannot be cancelled from its current state.');
            }

            $auction->update([
                'status' => AuctionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_admin_id' => $adminId,
                'cancellation_reason' => $reason,
            ]);
            $auction->refresh();
            event(new AuctionCancelled($auction));

            return $auction;
        });
    }
}
