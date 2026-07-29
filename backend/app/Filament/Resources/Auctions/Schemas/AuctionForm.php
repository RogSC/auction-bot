<?php

namespace App\Filament\Resources\Auctions\Schemas;

use App\Models\Artwork;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('artwork_id')->options(Artwork::query()->pluck('title', 'id'))->required(),
                TextInput::make('start_price_cents')->numeric()->minValue(1)->required(),
                TextInput::make('bid_increment_cents')->numeric()->minValue(1)->required(),
                DateTimePicker::make('starts_at')->required(),
                DateTimePicker::make('ends_at')->required(),
                TextInput::make('extension_threshold_seconds')->numeric()->default(120)->required(),
                TextInput::make('extension_duration_seconds')->numeric()->default(120)->required(),
            ]);
    }
}
