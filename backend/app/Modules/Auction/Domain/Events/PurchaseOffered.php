<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Models\PurchaseOffer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PurchaseOffered implements ShouldDispatchAfterCommit
{
    public function __construct(public PurchaseOffer $offer) {}
}
