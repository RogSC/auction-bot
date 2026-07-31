<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\Auction;
use App\Models\Artwork;
use App\Models\Release;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Support\Str;

final readonly class ShowCurrentReleaseLots
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(int $chatId): void
    {
        $release = Release::query()
            ->with('artworks')
            ->where('status', ReleaseStatus::Running)
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('starts_at')
            ->first();
        if ($release === null) {
            $this->client->sendMessage($chatId, 'Сейчас нет активного выпуска.');

            return;
        }

        $auctions = Auction::query()
            ->whereIn('artwork_id', $release->artworks->modelKeys())
            ->whereIn('status', [AuctionStatus::Scheduled, AuctionStatus::Active])
            ->get()
            ->keyBy('artwork_id');
        $sent = false;

        foreach ($release->artworks->values() as $index => $artwork) {
            $auction = $auctions->get($artwork->id);
            if ($auction === null) {
                continue;
            }

            $this->client->sendPhoto(
                $chatId,
                $artwork->preview_disk,
                $artwork->preview_path,
                $this->caption($index + 1, $artwork, $auction),
                'release-lots-'.Str::uuid(),
            );
            $sent = true;
        }

        if (! $sent) {
            $this->client->sendMessage($chatId, 'В текущем выпуске пока нет доступных лотов.');
        }
    }

    private function caption(int $lotNumber, Artwork $artwork, Auction $auction): string
    {
        return sprintf(
            "Лот №%d\nАвтор: %s\nНазвание: %s\nГод: %s\n%s\nСтартовая цена: $%d.%02d",
            $lotNumber,
            $artwork->artist_name ?? 'Автор не указан',
            $artwork->title,
            $artwork->creation_year ?? 'Не указан',
            Str::limit($artwork->description, 700),
            intdiv($auction->start_price_cents, 100),
            $auction->start_price_cents % 100,
        );
    }
}
