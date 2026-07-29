<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\PurchaseOffer;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Enums\BidStatus;
use App\Modules\Auction\Domain\Enums\PurchaseOfferStatus;
use App\Modules\Auction\Domain\Events\PurchaseOffered;
use Illuminate\Support\Facades\DB;

final readonly class OfferPurchaseToSecondBidder
{
    public function __construct(private AuctionSettings $settings) {}

    public function handle(int $auctionId, int $adminId): PurchaseOffer
    {
        return DB::transaction(function () use ($auctionId, $adminId): PurchaseOffer {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);
            if ($auction->status !== AuctionStatus::AwaitingPayment || $auction->auction_winner_id === null) {
                throw new AuctionOperationException('Auction cannot be offered to another bidder.');
            }
            if (PurchaseOffer::query()->where('auction_id', $auction->id)->where('status', PurchaseOfferStatus::Pending)->exists()) {
                throw new AuctionOperationException('A pending offer already exists.');
            }
            $bid = Bid::query()->where('auction_id', $auction->id)->where('user_id', '!=', $auction->auction_winner_id)->whereIn('status', [BidStatus::Active, BidStatus::Outbid])->orderByDesc('amount_cents')->orderByDesc('id')->first();
            if (! $bid) {
                throw new AuctionOperationException('No eligible second bidder exists.');
            }
            $expiresAt = now()->addHours($this->settings->integer('auction.payment_window_hours'));
            $offer = PurchaseOffer::query()->create(['auction_id' => $auction->id, 'bid_id' => $bid->id, 'offered_to_user_id' => $bid->user_id, 'amount_cents' => $bid->amount_cents, 'status' => PurchaseOfferStatus::Pending, 'offered_at' => now(), 'expires_at' => $expiresAt, 'created_by_admin_id' => $adminId]);
            $auction->update(['buyer_id' => $bid->user_id, 'accepted_bid_id' => $bid->id, 'payment_due_at' => $expiresAt]);
            event(new PurchaseOffered($offer));

            return $offer;
        });
    }
}
