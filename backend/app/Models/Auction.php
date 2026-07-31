<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $auction): void {
            if ($auction->getAttribute('current_price_cents') === null) {
                $auction->current_price_cents = $auction->start_price_cents;
            }
        });
    }

    protected function casts(): array
    {
        return ['status' => AuctionStatus::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'payment_due_at' => 'immutable_datetime'];
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }
}
