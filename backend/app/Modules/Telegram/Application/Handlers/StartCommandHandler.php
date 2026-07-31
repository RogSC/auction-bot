<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\User;
use App\Modules\Release\Application\FindFirstArtworkDelivery;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class StartCommandHandler
{
    public function __construct(
        private TelegramBotApiClient $client,
        private TelegramMessageRenderer $renderer,
        private FindFirstArtworkDelivery $findFirstArtworkDelivery,
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

        $this->client->sendMessage($chatId, $this->renderer->releaseWelcome($this->findFirstArtworkDelivery->handle()), disableNotification: true);
    }
}
