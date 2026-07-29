<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAuctionRules
{
    public function handle(int $auctionId, AuctionRules $rules): Auction
    {
        return DB::transaction(function () use ($auctionId, $rules): Auction {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);

            if ($auction->status->isTerminal()) {
                throw new AuctionOperationException('Terminal auctions cannot be edited.');
            }

            $changes = [
                'start_price_cents' => $rules->startPriceCents,
                'bid_increment_cents' => $rules->bidIncrementCents,
                'starts_at' => $rules->startsAt,
                'ends_at' => $rules->endsAt,
                'extension_threshold_seconds' => $rules->extensionThresholdSeconds,
                'extension_duration_seconds' => $rules->extensionDurationSeconds,
            ];

            $rulesChanged = $auction->start_price_cents !== $rules->startPriceCents
                || $auction->bid_increment_cents !== $rules->bidIncrementCents
                || ! $auction->starts_at->equalTo($rules->startsAt)
                || ! $auction->ends_at->equalTo($rules->endsAt)
                || $auction->extension_threshold_seconds !== $rules->extensionThresholdSeconds
                || $auction->extension_duration_seconds !== $rules->extensionDurationSeconds;

            if ($rulesChanged && Bid::query()->where('auction_id', $auction->id)->exists()) {
                throw new AuctionOperationException('Auction rules cannot be changed after the first bid.');
            }

            if ($rulesChanged) {
                $auction->update($changes + ['current_price_cents' => $rules->startPriceCents]);
            }

            return $auction->refresh();
        });
    }
}
