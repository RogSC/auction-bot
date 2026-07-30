<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Schemas;

use App\Models\Artwork;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseNotificationMode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReleaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Release')
                ->schema([
                    TextInput::make('title')->required()->maxLength(255),
                    DateTimePicker::make('starts_at')->required(),
                    DateTimePicker::make('ends_at')->after('starts_at'),
                    TextInput::make('timeline_scale_basis_points')
                        ->label('Timeline scale (basis points)')
                        ->numeric()
                        ->minValue(1)
                        ->default(10000)
                        ->required(),
                ])->columns(2),
            Section::make('Works in catalog')
                ->schema([
                    Repeater::make('releaseArtworks')
                        ->relationship()
                        ->orderColumn('position')
                        ->schema([
                            Select::make('artwork_id')
                                ->label('Artwork')
                                ->options(fn (): array => Artwork::query()->orderBy('title')->pluck('title', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->addActionLabel('Add artwork')
                        ->defaultItems(0),
                ]),
            Section::make('Shared timeline')
                ->description('Events use the common release calendar. A late subscriber receives only events scheduled after their subscription time.')
                ->schema([
                    Repeater::make('events')
                        ->relationship()
                        ->orderColumn('sequence')
                        ->schema([
                            Select::make('type')->options(self::options(ReleaseEventType::cases()))->required(),
                            Select::make('artwork_id')
                                ->label('Artwork')
                                ->options(fn (): array => Artwork::query()->orderBy('title')->pluck('title', 'id')->all())
                                ->searchable(),
                            TextInput::make('auction_id')->numeric(),
                            DateTimePicker::make('scheduled_at')->required(),
                            Select::make('notification_mode')
                                ->options(self::options(ReleaseNotificationMode::cases()))
                                ->default(ReleaseNotificationMode::Loud->value)
                                ->required(),
                            Textarea::make('payload.text')
                                ->label('Text / explanation')
                                ->columnSpanFull(),
                            Textarea::make('payload.caption')
                                ->label('Artwork caption (optional)')
                                ->columnSpanFull(),
                        ])->columns(2)
                        ->addActionLabel('Add scheduled event')
                        ->defaultItems(0),
                ]),
        ]);
    }

    /** @param array<int, \BackedEnum> $cases
     *  @return array<string, string>
     */
    private static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(
            fn (\BackedEnum $case): array => [$case->value => str($case->name)->headline()->toString()],
        )->all();
    }
}
