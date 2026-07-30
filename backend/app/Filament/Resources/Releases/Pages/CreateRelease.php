<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRelease extends CreateRecord
{
    protected static string $resource = ReleaseResource::class;

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_admin_id'] = Filament::auth()->id();
        $data['status'] = ReleaseStatus::Draft->value;

        return $data;
    }
}
