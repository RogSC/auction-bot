<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\User;
use App\Modules\Participant\Application\AcceptTerms;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class TermsCallbackHandler
{
    public function __construct(private TelegramBotApiClient $client, private AcceptTerms $acceptTerms) {}

    /** @param array<string, mixed> $callbackQuery */
    public function handle(array $callbackQuery): bool
    {
        $data = $callbackQuery['data'] ?? null;
        $telegramId = (int) ($callbackQuery['from']['id'] ?? 0);
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        if (! is_string($data) || ! str_starts_with($data, 'terms_accept:') || $telegramId <= 0 || $chatId <= 0) {
            return false;
        }

        $user = User::query()->where('telegram_id', $telegramId)->first();
        if ($user === null) {
            return true;
        }

        $this->acceptTerms->handle($user->id, substr($data, strlen('terms_accept:')));
        $this->client->sendMessage($chatId, 'Правила приняты. Теперь можно делать ставки.');

        return true;
    }
}
