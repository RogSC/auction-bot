<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Enums\BidStatus;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Participant\Application\TermsVersion;
use Illuminate\Support\Facades\DB;

final readonly class PlaceNextBid
{
    public function __construct(private TermsVersion $termsVersion) {}

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
            if ($user->accepted_terms_at === null || $user->accepted_terms_version !== $this->termsVersion->current()) {
                throw new AuctionOperationException('Terms must be accepted before bidding.');
            }
            $amount = $auction->current_price_cents + $auction->bid_increment_cents;
            $outbidUserId = $auction->current_leader_id;
            Bid::query()->where('auction_id', $auction->id)->where('status', BidStatus::Active)->update(['status' => BidStatus::Outbid]);
            $bid = Bid::query()->create(['auction_id' => $auction->id, 'user_id' => $user->id, 'amount_cents' => $amount, 'status' => BidStatus::Active, 'placed_at' => now()]);
            $previousEndsAt = $auction->ends_at;
            $endsAt = $previousEndsAt;
            if ($endsAt->diffInSeconds(now(), false) <= $auction->extension_threshold_seconds) {
                $endsAt = now()->addSeconds($auction->extension_duration_seconds);
            }
            $auction->update(['current_price_cents' => $amount, 'current_leader_id' => $user->id, 'ends_at' => $endsAt, 'version' => $auction->version + 1]);
            $auction->refresh();
            event(new BidPlaced($auction, $bid, $outbidUserId));
            if (! $endsAt->equalTo($previousEndsAt)) {
                event(new AuctionExtended($auction, $previousEndsAt));
            }

            return $bid;
        });
    }
}
