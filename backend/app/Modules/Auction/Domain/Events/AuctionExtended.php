<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Models\Auction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class AuctionExtended implements ShouldDispatchAfterCommit
{
    public function __construct(public Auction $auction, public CarbonImmutable $previousEndsAt) {}
}
