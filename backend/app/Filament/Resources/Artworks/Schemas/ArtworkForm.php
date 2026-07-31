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
                TextInput::make('artist_name')->label('Artist')->maxLength(255),
                TextInput::make('creation_year')->label('Year')->numeric()->minValue(1000)->maxValue(9999),
                Textarea::make('description')->required()->columnSpanFull(),
                Textarea::make('ownership_terms')->label('Ownership terms')->columnSpanFull(),
                FileUpload::make('preview_path')->disk('local')->directory('artwork-previews')->image()->required(),
            ]);
    }
}
