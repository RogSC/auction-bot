<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\User;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendBidPlacedNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(BidPlaced $event): void
    {
        $outbidUser = $event->outbidUserId === null ? null : User::query()->find($event->outbidUserId);
        if ($outbidUser?->telegram_id !== null) {
            $this->client->sendMessage($outbidUser->telegram_id, "Вашу ставку на аукционе #{$event->auction->id} перебили.", idempotencyKey: "bid-outbid-{$event->bid->id}-{$outbidUser->id}");
        }
    }
}
