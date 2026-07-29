<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
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
