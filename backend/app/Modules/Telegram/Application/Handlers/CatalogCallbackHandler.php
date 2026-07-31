<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class CatalogCallbackHandler
{
    public function __construct(private TelegramBotApiClient $client) {}

    /** @param array<string, mixed> $callbackQuery */
    public function handle(array $callbackQuery): bool
    {
        $data = $callbackQuery['data'] ?? null;
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        if (! is_string($data) || ! str_starts_with($data, 'catalog_pending:') || $chatId <= 0) {
            return false;
        }

        $this->client->sendMessage($chatId, 'Каталог будет добавлен сюда позже.');

        return true;
    }
}
