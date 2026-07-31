<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use Illuminate\Support\Collection;
use App\Models\Release;
use App\Modules\Release\Domain\Enums\ReleaseStatus;

final class AuctionReplyKeyboard
{
    /** @param Collection<int, \App\Models\Auction> $auctions
     *  @return array<string, mixed>
     */
    public function forAuctions(Collection $auctions): array
    {
        $hasCurrentRelease = Release::query()
            ->where('status', ReleaseStatus::Running)
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();

        if ($auctions->isEmpty() && ! $hasCurrentRelease) {
            return ['remove_keyboard' => true];
        }

        $keyboard = $hasCurrentRelease ? [[['text' => 'Все лоты']]] : [];
        foreach ($auctions as $auction) {
            $keyboard[] = [['text' => "Аукцион #{$auction->id}"]];
        }

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'is_persistent' => true,
            'one_time_keyboard' => false,
        ];
    }
}
