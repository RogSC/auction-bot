<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class BidPlaced implements ShouldDispatchAfterCommit
{
    public function __construct(public Auction $auction, public Bid $bid) {}
}
