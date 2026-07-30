<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Filament\Resources\Artworks\ArtworkResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

class CreateArtwork extends CreateRecord
{
    protected static string $resource = ArtworkResource::class;

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $adminId = Filament::auth()->id();

        if (! is_int($adminId) && ! ctype_digit((string) $adminId)) {
            throw new LogicException('An authenticated administrator is required to create artwork.');
        }

        $data['created_by_admin_id'] = (int) $adminId;
        $data['preview_disk'] = 'local';

        return $data;
    }
}
