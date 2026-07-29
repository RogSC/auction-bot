<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => AuctionStatus::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'payment_due_at' => 'immutable_datetime'];
    }
}
