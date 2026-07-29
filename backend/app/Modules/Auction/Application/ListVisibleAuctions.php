<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use App\Models\Auction;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListVisibleAuctions
{
    /** @return Collection<int, Auction> */
    public function handle(): Collection
    {
        return Auction::query()
            ->where('status', AuctionStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderBy('ends_at')
            ->get();
    }
}
