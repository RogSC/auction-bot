<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class CallbackQueryHandler
{
    public function __construct(private TelegramBotApiClient $client, private MenuCallbackHandler $menuCallbackHandler, private BidCallbackHandler $bidCallbackHandler, private TermsCallbackHandler $termsCallbackHandler, private CatalogCallbackHandler $catalogCallbackHandler) {}

    /** @param array<string, mixed> $callbackQuery */
    public function handle(array $callbackQuery): void
    {
        $callbackId = $callbackQuery['id'] ?? null;
        if (is_string($callbackId) && $callbackId !== '') {
            $this->client->answerCallbackQuery($callbackId);
        }

        if (! $this->termsCallbackHandler->handle($callbackQuery) && ! $this->menuCallbackHandler->handle($callbackQuery) && ! $this->catalogCallbackHandler->handle($callbackQuery)) {
            $this->bidCallbackHandler->handle($callbackQuery);
        }
    }
}
