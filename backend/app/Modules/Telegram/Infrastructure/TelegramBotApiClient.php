<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class TelegramBotApiClient
{
    public function __construct(private Factory $http) {}

    /** @param array<string, mixed> $parameters */
    /** @return array<string, mixed> */
    public function call(string $method, array $parameters): array
    {
        $token = config('telegram.bot_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $response = $this->http->baseUrl(config('telegram.api_base_url'))->post("bot{$token}/{$method}", $parameters);

        return $response->throw()->json();
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null, ?string $idempotencyKey = null): void
    {
        $parameters = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup !== null) {
            $parameters['reply_markup'] = $replyMarkup;
        }

        $key = $idempotencyKey ?? (string) Str::uuid();
        $inserted = DB::table('telegram_messages')->insertOrIgnore([
            'chat_id' => $chatId,
            'direction' => 'outbound',
            'type' => 'sendMessage',
            'idempotency_key' => $key,
            'payload' => json_encode($parameters, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            return;
        }

        try {
            $response = $this->call('sendMessage', $parameters);
            DB::table('telegram_messages')->where('idempotency_key', $key)->update([
                'telegram_message_id' => $response['result']['message_id'] ?? null,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('telegram_messages')->where('idempotency_key', $key)->update(['failed_at' => now(), 'failure_reason' => $exception->getMessage(), 'updated_at' => now()]);

            throw $exception;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }
}
