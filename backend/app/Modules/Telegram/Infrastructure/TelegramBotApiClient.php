<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure;

use Illuminate\Http\Client\Factory;
use RuntimeException;

final readonly class TelegramBotApiClient
{
    public function __construct(private Factory $http) {}

    /** @param array<string, mixed> $parameters */
    public function call(string $method, array $parameters): void
    {
        $token = config('telegram.bot_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $response = $this->http->baseUrl(config('telegram.api_base_url'))->post("bot{$token}/{$method}", $parameters);
        $response->throw();
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $parameters = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup !== null) {
            $parameters['reply_markup'] = $replyMarkup;
        }

        $this->call('sendMessage', $parameters);
    }

    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }
}
