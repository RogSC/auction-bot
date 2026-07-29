<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Models\Bid;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Enums\BidStatus;
use Illuminate\Support\Facades\DB;

final readonly class CancelBid
{
    public function handle(int $auctionId, int $bidId, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($auctionId, $bidId, $adminId, $reason): void {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);
            if ($auction->status !== AuctionStatus::Active) {
                throw new AuctionOperationException('Only active auctions can have bids cancelled.');
            }
            $bid = Bid::query()->where('auction_id', $auction->id)->findOrFail($bidId);
            if (! $bid->status->isValidForRanking()) {
                throw new AuctionOperationException('Bid is not valid for cancellation.');
            }
            $bid->update(['status' => BidStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_admin_id' => $adminId, 'cancellation_reason' => $reason]);
            $leader = Bid::query()->where('auction_id', $auction->id)->whereIn('status', [BidStatus::Active, BidStatus::Outbid])->orderByDesc('amount_cents')->orderByDesc('id')->first();
            Bid::query()->where('auction_id', $auction->id)->whereIn('status', [BidStatus::Active, BidStatus::Outbid])->update(['status' => BidStatus::Outbid]);
            if ($leader) {
                $leader->update(['status' => BidStatus::Active]);
            }
            $auction->update(['current_price_cents' => $leader?->amount_cents ?? $auction->start_price_cents, 'current_leader_id' => $leader?->user_id, 'version' => $auction->version + 1]);
        });
    }
}
