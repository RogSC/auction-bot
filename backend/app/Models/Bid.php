<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Auction\Domain\Enums\BidStatus;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => BidStatus::class, 'placed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
