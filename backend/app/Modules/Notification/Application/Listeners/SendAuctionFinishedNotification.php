<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\Bid;
use App\Models\User;
use App\Modules\Auction\Domain\Events\AuctionFinished;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendAuctionFinishedNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(AuctionFinished $event): void
    {
        $userIds = Bid::query()->where('auction_id', $event->auction->id)->distinct()->pluck('user_id');
        User::query()->whereIn('id', $userIds)->whereNotNull('telegram_id')->each(fn (User $user) => $this->client->sendMessage($user->telegram_id, "Аукцион #{$event->auction->id} завершён."));
    }
}
