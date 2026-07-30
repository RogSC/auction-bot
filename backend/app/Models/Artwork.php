<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artwork extends Model
{
    protected $guarded = [];

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_artworks')
            ->withPivot('position')
            ->withTimestamps();
    }
}
