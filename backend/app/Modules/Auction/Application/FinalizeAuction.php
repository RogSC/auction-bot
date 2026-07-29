<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Models\Bid;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Enums\BidStatus;
use Illuminate\Support\Facades\DB;

final readonly class FinalizeAuction
{
    public function __construct(private AuctionSettings $settings) {}

    public function handle(int $auctionId): Auction
    {
        return DB::transaction(function () use ($auctionId): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);
            if ($auction->status !== AuctionStatus::Active || $auction->ends_at->isFuture()) {
                return $auction;
            }
            $bid = Bid::query()->where('auction_id', $auction->id)->whereIn('status', [BidStatus::Active, BidStatus::Outbid])->orderByDesc('amount_cents')->orderByDesc('id')->first();
            if (! $bid) {
                $auction->update(['status' => AuctionStatus::NoSale]);

                return $auction->refresh();
            }
            Bid::query()->where('auction_id', $auction->id)->whereIn('status', [BidStatus::Active, BidStatus::Outbid])->update(['status' => BidStatus::Outbid]);
            $bid->update(['status' => BidStatus::Winning]);
            $auction->update(['status' => AuctionStatus::AwaitingPayment, 'auction_winner_id' => $bid->user_id, 'winning_bid_id' => $bid->id, 'payment_due_at' => now()->addHours($this->settings->integer('auction.payment_window_hours'))]);

            return $auction->refresh();
        });
    }
}
