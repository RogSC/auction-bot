<?php

namespace App\Filament\Resources\Auctions\Pages;

use App\Filament\Resources\Auctions\AuctionResource;
use App\Models\Auction;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\AuctionRules;
use App\Modules\Auction\Application\UpdateAuctionRules;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAuction extends EditRecord
{
    protected static string $resource = AuctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Auction $record */
        try {
            $auction = app(UpdateAuctionRules::class)->handle($record->id, new AuctionRules(
                startPriceCents: (int) $data['start_price_cents'],
                bidIncrementCents: (int) $data['bid_increment_cents'],
                startsAt: CarbonImmutable::parse($data['starts_at']),
                endsAt: CarbonImmutable::parse($data['ends_at']),
                extensionThresholdSeconds: (int) $data['extension_threshold_seconds'],
                extensionDurationSeconds: (int) $data['extension_duration_seconds'],
            ));
        } catch (AuctionOperationException $exception) {
            throw ValidationException::withMessages(['start_price_cents' => $exception->getMessage()]);
        }

        $auction->update(['artwork_id' => $data['artwork_id']]);

        return $auction;
    }
}
