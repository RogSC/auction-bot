<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\User;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\PaymentRequested;
use App\Modules\Auction\Domain\Events\PurchaseOffered;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendLifecycleNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(AuctionStarted|PaymentRequested|AuctionCancelled|PurchaseOffered $event): void
    {
        match (true) {
            $event instanceof AuctionStarted => null,
            $event instanceof PaymentRequested => $this->sendToUser($event->auction->auction_winner_id, 'Пожалуйста, подтвердите оплату выигравшей ставки.'),
            $event instanceof AuctionCancelled => null,
            $event instanceof PurchaseOffered => $this->sendToUser($event->offer->offered_to_user_id, "Вам предложена покупка этой работы за {$event->offer->amount_cents} центов."),
        };
    }

    private function sendToUser(?int $userId, string $message): void
    {
        $user = $userId === null ? null : User::query()->find($userId);
        if ($user?->telegram_id !== null) {
            $this->client->sendMessage($user->telegram_id, $message);
        }
    }
}
