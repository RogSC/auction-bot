<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Release;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('telegram.webhook_secret', 'test-webhook-secret');
    config()->set('telegram.bot_token', 'test-bot-token');
});

it('routes the start command, creates a participant, and calls the Telegram client', function (): void {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson('/api/telegram/webhook', [
        'update_id' => 700_003,
        'message' => [
            'text' => '/start',
            'chat' => ['id' => 900_001],
            'from' => ['id' => 800_001, 'first_name' => 'Ada', 'username' => 'ada'],
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'])->assertOk();

    expect(DB::table('users')->where('telegram_id', 800_001)->value('bidder_code'))->toMatch('/^BIDDER-\d{6}$/');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
        && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'menu:active'
        && $request['reply_markup']['inline_keyboard'][1][0]['callback_data'] === 'menu:bids'
        && $request['reply_markup']['inline_keyboard'][2][0]['callback_data'] === 'menu:completed'
        && $request['reply_markup']['inline_keyboard'][3][0]['callback_data'] === 'menu:rules');
});

it('renders an auction without Telegram identifiers or usernames', function (): void {
    $auction = new Auction(['current_price_cents' => 12_000, 'ends_at' => now()]);
    $text = app(TelegramMessageRenderer::class)->auction($auction, 'BIDDER-000123');

    expect($text)->toContain('BIDDER-000123')
        ->not->toContain('telegram_id')
        ->not->toContain('username');
});

it('subscribes a participant to the current release from the Telegram command menu', function (): void {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 43]])]);
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Admin',
        'email' => 'menu-subscribe@example.test',
        'password' => 'password',
    ]);
    $release = Release::query()->create([
        'title' => 'Current release',
        'status' => ReleaseStatus::Running,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'created_by_admin_id' => $adminId,
    ]);

    $this->postJson('/api/telegram/webhook', [
        'update_id' => 700_004,
        'message' => [
            'text' => '/subscribe',
            'chat' => ['id' => 900_002],
            'from' => ['id' => 800_002, 'first_name' => 'Grace'],
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'])->assertOk();

    $userId = DB::table('users')->where('telegram_id', 800_002)->value('id');
    expect(DB::table('release_subscriptions')
        ->where('release_id', $release->id)
        ->where('user_id', $userId)
        ->exists())->toBeTrue();
});

it('records outgoing messages and suppresses duplicate idempotency keys', function (): void {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
    $client = app(TelegramBotApiClient::class);

    $client->sendMessage(900_002, 'Queued notification', idempotencyKey: 'notification-unique-key');
    $client->sendMessage(900_002, 'Queued notification', idempotencyKey: 'notification-unique-key');

    expect(DB::table('telegram_messages')->where('idempotency_key', 'notification-unique-key')->count())->toBe(1)
        ->and(DB::table('telegram_messages')->where('idempotency_key', 'notification-unique-key')->value('telegram_message_id'))->toBe(42);
    Http::assertSentCount(1);
});

it('rejects webhook requests without the configured Telegram secret token', function (): void {
    $this->postJson('/api/telegram/webhook', ['update_id' => 700_001])
        ->assertForbidden();

    expect(DB::table('processed_telegram_updates')->count())->toBe(0);
});

it('processes each Telegram update id only once', function (): void {
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'];

    $this->postJson('/api/telegram/webhook', ['update_id' => 700_002], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'accepted');
    $this->postJson('/api/telegram/webhook', ['update_id' => 700_002], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'duplicate');

    expect(DB::table('processed_telegram_updates')->where('update_id', 700_002)->count())->toBe(1);
});
