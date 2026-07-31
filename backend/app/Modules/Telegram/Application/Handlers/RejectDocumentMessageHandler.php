<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class RejectDocumentMessageHandler
{
    public function __construct(private TelegramBotApiClient $client) {}

    /** @param array<string, mixed> $message */
    public function handle(array $message): bool
    {
        if (! isset($message['document'])) {
            return false;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $messageId = $message['message_id'] ?? null;
        if ($chatId <= 0 || ! is_int($messageId)) {
            return true;
        }

        $this->client->deleteMessage($chatId, $messageId, "telegram-document-rejected-{$chatId}-{$messageId}");

        return true;
    }
}
