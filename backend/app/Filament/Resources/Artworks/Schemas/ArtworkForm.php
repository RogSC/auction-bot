<?php

namespace App\Filament\Resources\Artworks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ArtworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('description')->required()->columnSpanFull(),
                FileUpload::make('preview_path')->disk('local')->directory('artwork-previews')->image()->required(),
                TextInput::make('preview_disk')->default('local')->required()->hidden(),
                TextInput::make('created_by_admin_id')->numeric()->required(),
            ]);
    }
}
