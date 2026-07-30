<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Release\Domain\Enums\ReleaseEventStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseNotificationMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReleaseEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ReleaseEventType::class,
            'notification_mode' => ReleaseNotificationMode::class,
            'status' => ReleaseEventStatus::class,
            'payload' => 'array',
            'scheduled_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ReleaseDelivery::class);
    }
}
