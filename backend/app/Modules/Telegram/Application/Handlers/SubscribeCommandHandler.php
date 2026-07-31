<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\User;
use App\Modules\Release\Application\SubscribeToCurrentRelease;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class SubscribeCommandHandler
{
    public function __construct(
        private TelegramBotApiClient $client,
        private TelegramMessageRenderer $renderer,
        private SubscribeToCurrentRelease $subscribeToCurrentRelease,
    ) {}

    /** @param array<string, mixed> $message */
    public function handle(array $message): void
    {
        $from = $message['from'] ?? [];
        $telegramId = (int) ($from['id'] ?? 0);
        $chatId = (int) ($message['chat']['id'] ?? 0);
        if ($telegramId <= 0 || $chatId <= 0) {
            return;
        }

        $user = User::query()->firstOrCreate(['telegram_id' => $telegramId], [
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
        ]);
        if ($user->bidder_code === null) {
            $user->update(['bidder_code' => sprintf('BIDDER-%06d', $user->id)]);
        }

        $subscription = $this->subscribeToCurrentRelease->handle($user);
        $text = $subscription === null
            ? 'Сейчас нет активного выпуска. Попробуйте снова после его запуска.'
            : $this->renderer->releaseWelcome();

        $this->client->sendMessage($chatId, $text, disableNotification: true);
    }
}
