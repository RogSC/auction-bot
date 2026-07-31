<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\User;
use App\Modules\Auction\Application\ListVisibleAuctions;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class SyncAuctionReplyKeyboard
{
    public function __construct(
        private ListVisibleAuctions $visibleAuctions,
        private AuctionReplyKeyboard $keyboard,
        private TelegramBotApiClient $client,
    ) {}

    public function handle(string $eventKey, ?string $message = null): void
    {
        $auctions = $this->visibleAuctions->handle();
        $replyMarkup = $this->keyboard->forAuctions($auctions);
        $text = $message ?? ($auctions->isEmpty()
            ? 'Активных аукционов сейчас нет.'
            : 'Меню активных аукционов обновлено.');

        User::query()
            ->whereNotNull('telegram_id')
            ->orderBy('id')
            ->each(function (User $user) use ($eventKey, $replyMarkup, $text): void {
                $this->sendToUser($user, $eventKey, $replyMarkup, $text);
            });
    }

    public function sendCurrentToUser(User $user): void
    {
        if ($user->telegram_id === null) {
            return;
        }

        $auctions = $this->visibleAuctions->handle();
        $this->sendToUser(
            $user,
            'user-'.$user->id.'-'.now()->format('YmdHi'),
            $this->keyboard->forAuctions($auctions),
            $auctions->isEmpty() ? 'Активных аукционов сейчас нет.' : 'Меню активных аукционов обновлено.',
        );
    }

    /** @param array<string, mixed> $replyMarkup */
    private function sendToUser(User $user, string $eventKey, array $replyMarkup, string $text): void
    {
        $this->client->sendMessage(
            $user->telegram_id,
            $text,
            $replyMarkup,
            "auction-reply-keyboard-{$eventKey}-user-{$user->id}",
        );
    }
}
