<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use Illuminate\Support\Collection;

final class AuctionReplyKeyboard
{
    /** @param Collection<int, \App\Models\Auction> $auctions
     *  @return array<string, mixed>
     */
    public function forAuctions(Collection $auctions): array
    {
        if ($auctions->isEmpty()) {
            return ['remove_keyboard' => true];
        }

        return [
            'keyboard' => $auctions
                ->map(fn ($auction): array => [['text' => "Аукцион #{$auction->id}"]])
                ->all(),
            'resize_keyboard' => true,
            'is_persistent' => true,
            'one_time_keyboard' => false,
        ];
    }
}
