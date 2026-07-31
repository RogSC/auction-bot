<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\User;
use App\Modules\Auction\Domain\Events\PaymentConfirmed;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendPaymentConfirmedNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(PaymentConfirmed $event): void
    {
        $user = User::query()->find($event->auction->buyer_id);
        if ($user?->telegram_id !== null) {
            $this->client->sendMessage($user->telegram_id, 'Оплата подтверждена. Покупка завершена.', idempotencyKey: "payment-confirmed-{$event->auction->id}");
        }
    }
}
