<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Release\Domain\Enums\ReleaseDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReleaseDeliveryStatus::class,
            'sent_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function releaseEvent(): BelongsTo
    {
        return $this->belongsTo(ReleaseEvent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
