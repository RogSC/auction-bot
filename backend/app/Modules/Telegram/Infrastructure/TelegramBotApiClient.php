<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure;

use Illuminate\Http\Client\Factory;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class TelegramBotApiClient
{
    public function __construct(private Factory $http, private FilesystemFactory $filesystems) {}

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
    public function sendMessage(
        int $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?string $idempotencyKey = null,
        bool $disableNotification = false,
        ?int $replyToMessageId = null,
    ): ?int
    {
        $parameters = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup !== null) {
            $parameters['reply_markup'] = $replyMarkup;
        }
        if ($disableNotification) {
            $parameters['disable_notification'] = true;
        }
        if ($replyToMessageId !== null) {
            $parameters['reply_parameters'] = ['message_id' => $replyToMessageId];
        }

        $key = $idempotencyKey ?? (string) Str::uuid();
        if (! $this->prepareOutbound($chatId, 'sendMessage', $parameters, $key)) {
            return DB::table('telegram_messages')->where('idempotency_key', $key)->value('telegram_message_id');
        }

        try {
            $response = $this->call('sendMessage', $parameters);
            $messageId = $response['result']['message_id'] ?? null;
            DB::table('telegram_messages')->where('idempotency_key', $key)->update([
                'telegram_message_id' => $messageId,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'updated_at' => now(),
            ]);

            return is_int($messageId) ? $messageId : null;
        } catch (\Throwable $exception) {
            DB::table('telegram_messages')->where('idempotency_key', $key)->update(['failed_at' => now(), 'failure_reason' => $exception->getMessage(), 'updated_at' => now()]);

            throw $exception;
        }
    }

    public function sendPhoto(
        int $chatId,
        string $disk,
        string $path,
        ?string $caption = null,
        ?string $idempotencyKey = null,
        bool $disableNotification = false,
        ?array $replyMarkup = null,
    ): ?int {
        if (! $this->filesystems->disk($disk)->exists($path)) {
            throw new RuntimeException("Telegram photo does not exist on disk [{$disk}]: {$path}");
        }

        $parameters = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $parameters['caption'] = $caption;
        }
        if ($disableNotification) {
            $parameters['disable_notification'] = true;
        }
        if ($replyMarkup !== null) {
            $parameters['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR);
        }

        $key = $idempotencyKey ?? (string) Str::uuid();
        if (! $this->prepareOutbound($chatId, 'sendPhoto', $parameters + ['photo_path' => $path, 'photo_disk' => $disk], $key)) {
            return DB::table('telegram_messages')->where('idempotency_key', $key)->value('telegram_message_id');
        }

        $stream = $this->filesystems->disk($disk)->readStream($path);
        if ($stream === false) {
            throw new RuntimeException("Unable to read Telegram photo from disk [{$disk}]: {$path}");
        }

        try {
            $token = config('telegram.bot_token');
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Telegram bot token is not configured.');
            }

            $response = $this->http->baseUrl(config('telegram.api_base_url'))
                ->attach('photo', $stream, basename($path))
                ->post("bot{$token}/sendPhoto", $parameters)
                ->throw()
                ->json();
            $messageId = $response['result']['message_id'] ?? null;
            DB::table('telegram_messages')->where('idempotency_key', $key)->update([
                'telegram_message_id' => $messageId,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'updated_at' => now(),
            ]);

            return is_int($messageId) ? $messageId : null;
        } catch (\Throwable $exception) {
            DB::table('telegram_messages')->where('idempotency_key', $key)->update(['failed_at' => now(), 'failure_reason' => $exception->getMessage(), 'updated_at' => now()]);

            throw $exception;
        } finally {
            fclose($stream);
        }
    }

    public function deleteMessage(int $chatId, int $messageId, ?string $idempotencyKey = null): void
    {
        $parameters = ['chat_id' => $chatId, 'message_id' => $messageId];
        $key = $idempotencyKey ?? (string) Str::uuid();
        if (! $this->prepareOutbound($chatId, 'deleteMessage', $parameters, $key)) {
            return;
        }

        try {
            $this->call('deleteMessage', $parameters);
            DB::table('telegram_messages')->where('idempotency_key', $key)->update(['sent_at' => now(), 'failed_at' => null, 'failure_reason' => null, 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            DB::table('telegram_messages')->where('idempotency_key', $key)->update(['failed_at' => now(), 'failure_reason' => $exception->getMessage(), 'updated_at' => now()]);

            throw $exception;
        }
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        $parameters = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text];
        if ($replyMarkup !== null) {
            $parameters['reply_markup'] = $replyMarkup;
        }

        $this->edit('editMessageText', $parameters);
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function editMessageCaption(int $chatId, int $messageId, string $caption, ?array $replyMarkup = null): void
    {
        $parameters = ['chat_id' => $chatId, 'message_id' => $messageId, 'caption' => $caption];
        if ($replyMarkup !== null) {
            $parameters['reply_markup'] = $replyMarkup;
        }

        $this->edit('editMessageCaption', $parameters);
    }

    /** @param array<string, mixed> $parameters */
    private function prepareOutbound(int $chatId, string $type, array $parameters, string $key): bool
    {
        $message = DB::table('telegram_messages')->where('idempotency_key', $key)->first();
        if ($message !== null && $message->sent_at !== null) {
            return false;
        }

        if ($message === null) {
            DB::table('telegram_messages')->insert([
                'chat_id' => $chatId,
                'direction' => 'outbound',
                'type' => $type,
                'idempotency_key' => $key,
                'payload' => json_encode($parameters, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    /** @param array<string, mixed> $parameters */
    private function edit(string $method, array $parameters): void
    {
        try {
            $this->call($method, $parameters);
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'message is not modified')) {
                return;
            }

            throw $exception;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }

    /** @param list<array{command: string, description: string}> $commands */
    public function setMyCommands(array $commands): void
    {
        $this->call('setMyCommands', ['commands' => $commands]);
    }

    public function deleteMyCommands(): void
    {
        $this->call('deleteMyCommands', []);
    }
}
