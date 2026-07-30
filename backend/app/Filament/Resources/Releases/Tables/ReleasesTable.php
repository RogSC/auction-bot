<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReleasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime()->sortable(),
                TextColumn::make('events_count')->counts('events')->label('Events'),
                TextColumn::make('subscriptions_count')->counts('subscriptions')->label('Subscribers'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
