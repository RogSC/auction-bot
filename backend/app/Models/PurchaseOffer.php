<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Auction\Domain\Enums\PurchaseOfferStatus;
use Illuminate\Database\Eloquent\Model;

class PurchaseOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => PurchaseOfferStatus::class, 'offered_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
