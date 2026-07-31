<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\ReleaseArtwork;
use App\Models\User;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendBidPlacedNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client, private TelegramMessageRenderer $renderer) {}

    public function handle(BidPlaced $event): void
    {
        $outbidUser = $event->outbidUserId === null ? null : User::query()->find($event->outbidUserId);
        if ($outbidUser?->telegram_id !== null) {
            $releaseArtwork = ReleaseArtwork::query()->with('artwork')->where('auction_id', $event->auction->id)->first();
            $lotNumber = $releaseArtwork?->position ?? $event->auction->id;
            $title = $releaseArtwork?->artwork?->title ?? 'Работа';
            $artist = $releaseArtwork?->artwork?->artist_name ?? 'Автор не указан';
            $message = "Вашу ставку перебили\nЛот №{$lotNumber}. {$title}, {$artist}";

            $this->client->sendMessage(
                $outbidUser->telegram_id,
                $message,
                $this->renderer->auctionKeyboard($event->auction),
                "bid-outbid-{$event->bid->id}-{$outbidUser->id}",
            );
        }
    }
}
