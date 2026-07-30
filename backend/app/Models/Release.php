<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Release extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReleaseStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'release_artworks')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('release_artworks.position');
    }

    public function releaseArtworks(): HasMany
    {
        return $this->hasMany(ReleaseArtwork::class)->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReleaseEvent::class)->orderBy('sequence');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ReleaseSubscription::class);
    }
}
