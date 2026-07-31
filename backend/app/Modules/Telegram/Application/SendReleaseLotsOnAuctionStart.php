<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\Auction;
use App\Models\Artwork;
use App\Models\Release;
use App\Models\ReleaseSubscription;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Support\Str;

final readonly class SendReleaseLotsOnAuctionStart
{
    public function __construct(private TelegramBotApiClient $client, private TelegramMessageRenderer $renderer) {}

    public function handle(int $startedAuctionId): void
    {
        $release = Release::query()
            ->with('releaseArtworks.artwork')
            ->where('status', ReleaseStatus::Running)
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('starts_at')
            ->first();
        if ($release === null) {
            return;
        }

        $auctionIds = $release->releaseArtworks->pluck('auction_id')->filter()->all();
        if (! in_array($startedAuctionId, $auctionIds, true)) {
            return;
        }

        $auctions = Auction::query()
            ->whereIn('id', $auctionIds)
            ->where('status', AuctionStatus::Active)
            ->get()
            ->keyBy('id');
        if ($auctions->isEmpty()) {
            return;
        }

        $users = ReleaseSubscription::query()
            ->where('release_id', $release->id)
            ->where('subscribed_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('unsubscribed_at')->orWhere('unsubscribed_at', '>', now()))
            ->with('user:id,telegram_id')
            ->get()
            ->pluck('user')
            ->filter(fn ($user) => $user?->telegram_id !== null);

        foreach ($users as $user) {
            $this->client->sendMessage(
                $user->telegram_id,
                $this->auctionStartedMessage($release),
                idempotencyKey: "release-auction-started-{$release->id}-user-{$user->id}",
            );

            foreach ($release->releaseArtworks->values() as $index => $releaseArtwork) {
                $auction = $auctions->get($releaseArtwork->auction_id);
                if ($auction === null || $releaseArtwork->artwork === null) {
                    continue;
                }

                $this->client->sendPhoto(
                    $user->telegram_id,
                    $releaseArtwork->artwork->preview_disk,
                    $releaseArtwork->artwork->preview_path,
                    $this->caption($index + 1, $releaseArtwork->artwork, $auction),
                    "release-auction-start-{$release->id}-user-{$user->id}-artwork-{$releaseArtwork->artwork->id}",
                    true,
                    $this->renderer->auctionKeyboard($auction),
                );
            }
        }
    }

    private function caption(int $lotNumber, Artwork $artwork, Auction $auction): string
    {
        return sprintf(
            "Лот №%d\nАвтор: %s\nНазвание: %s\nГод: %s\n%s\n\n%s",
            $lotNumber,
            $artwork->artist_name ?? 'Автор не указан',
            $artwork->title,
            $artwork->creation_year ?? 'Не указан',
            Str::limit($artwork->description, 700),
            $this->renderer->auction($auction, null),
        );
    }

    private function auctionStartedMessage(Release $release): string
    {
        $message = 'Аукцион начался. Все лоты доступны для ставок.';

        if ($release->ends_at !== null) {
            $message .= "\nОкончание аукциона: {$release->ends_at->format('d.m.Y H:i')}";
        }

        return $message;
    }
}
