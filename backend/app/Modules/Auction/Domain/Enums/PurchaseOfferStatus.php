<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Enums;

enum PurchaseOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
