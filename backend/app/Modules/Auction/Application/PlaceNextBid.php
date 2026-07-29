<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Enums\BidStatus;
use Illuminate\Support\Facades\DB;

final readonly class PlaceNextBid
{
    public function handle(int $auctionId, int $userId, int $viewVersion): Bid
    {
        return DB::transaction(function () use ($auctionId, $userId, $viewVersion): Bid {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);
            $user = User::query()->findOrFail($userId);
            if ($auction->status !== AuctionStatus::Active || $auction->ends_at->isPast()) {
                throw new AuctionOperationException('Auction is not active.');
            }
            if ($auction->version !== $viewVersion) {
                throw new AuctionOperationException('Auction view is stale.');
            }
            if ($user->accepted_terms_at === null) {
                throw new AuctionOperationException('Terms must be accepted before bidding.');
            }
            $amount = $auction->current_price_cents + $auction->bid_increment_cents;
            Bid::query()->where('auction_id', $auction->id)->where('status', BidStatus::Active)->update(['status' => BidStatus::Outbid]);
            $bid = Bid::query()->create(['auction_id' => $auction->id, 'user_id' => $user->id, 'amount_cents' => $amount, 'status' => BidStatus::Active, 'placed_at' => now()]);
            $endsAt = $auction->ends_at;
            if ($endsAt->diffInSeconds(now(), false) <= $auction->extension_threshold_seconds) {
                $endsAt = now()->addSeconds($auction->extension_duration_seconds);
            }
            $auction->update(['current_price_cents' => $amount, 'current_leader_id' => $user->id, 'ends_at' => $endsAt, 'version' => $auction->version + 1]);

            return $bid;
        });
    }
}
