<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Events\PaymentConfirmed;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmPayment
{
    public function handle(int $auctionId): Auction
    {
        return DB::transaction(function () use ($auctionId): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);
            if ($auction->status !== AuctionStatus::AwaitingPayment) {
                throw new AuctionOperationException('Auction is not awaiting payment.');
            }
            if ($auction->buyer_id === null) {
                $auction->buyer_id = $auction->auction_winner_id;
            }
            if ($auction->accepted_bid_id === null) {
                $auction->accepted_bid_id = $auction->winning_bid_id;
            }
            if ($auction->buyer_id === null || $auction->accepted_bid_id === null) {
                throw new AuctionOperationException('Auction has no buyer or accepted bid.');
            }
            $auction->status = AuctionStatus::Paid;
            $auction->save();
            event(new PaymentConfirmed($auction));

            return $auction;
        });
    }
}
