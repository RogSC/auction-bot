<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\Bid;
use App\Models\ReleaseArtwork;
use App\Models\User;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendAuctionExtendedNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(AuctionExtended $event): void
    {
        $userIds = Bid::query()->where('auction_id', $event->auction->id)->distinct()->pluck('user_id');
        $lotNumber = ReleaseArtwork::query()->where('auction_id', $event->auction->id)->value('position');
        $message = is_numeric($lotNumber)
            ? 'Аукцион на лот №'.(int) $lotNumber.' продлён.'
            : "Аукцион #{$event->auction->id} продлён.";

        User::query()->whereIn('id', $userIds)->whereNotNull('telegram_id')->each(
            fn (User $user) => $this->client->sendMessage($user->telegram_id, $message),
        );
    }
}
