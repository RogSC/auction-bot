<?php

declare(strict_types=1);

use App\Models\Artwork;
use App\Models\Auction;
use App\Models\Release;
use App\Models\User;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use App\Modules\Telegram\Application\RefreshReleaseAuctionCards;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('updates the already sent lot card after a bid', function (): void {
    config()->set('telegram.bot_token', 'test-bot-token');
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Admin',
        'email' => 'refresh-card-admin@example.test',
        'password' => 'password',
    ]);
    $user = User::query()->create([
        'telegram_id' => 900_100,
        'bidder_code' => 'BIDDER-000100',
        'first_name' => 'Ada',
    ]);
    $artwork = Artwork::query()->create([
        'title' => 'Lot',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/lot.jpg',
        'created_by_admin_id' => $adminId,
    ]);
    $release = Release::query()->create([
        'title' => 'Release',
        'status' => ReleaseStatus::Running,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'created_by_admin_id' => $adminId,
    ]);
    $auction = Auction::query()->create([
        'artwork_id' => $artwork->id,
        'status' => AuctionStatus::Active,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'current_price_cents' => 12_000,
        'current_leader_id' => $user->id,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'extension_threshold_seconds' => 120,
        'extension_duration_seconds' => 120,
    ]);
    $release->releaseArtworks()->create([
        'artwork_id' => $artwork->id,
        'position' => 1,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'auction_id' => $auction->id,
    ]);
    $oldCaption = "Lot 1\n".app(TelegramMessageRenderer::class)->auction($auction, null);
    DB::table('telegram_messages')->insert([
        'chat_id' => $user->telegram_id,
        'telegram_message_id' => 501,
        'direction' => 'outbound',
        'type' => 'sendPhoto',
        'idempotency_key' => "release-auction-start-{$release->id}-user-{$user->id}-artwork-{$artwork->id}",
        'payload' => json_encode(['caption' => $oldCaption], JSON_THROW_ON_ERROR),
        'sent_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(RefreshReleaseAuctionCards::class)->handle($auction->id);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottest-bot-token/editMessageCaption'
        && $request['message_id'] === 501
        && str_contains($request['caption'], '12000'));
    $payload = DB::table('telegram_messages')->where('telegram_message_id', 501)->value('payload');
    expect($payload)
        ->toContain('12000')
        ->toContain('Текущий лидер: Вы')
        ->and(substr_count((string) $payload, 'Текущая цена:'))->toBe(1);
});
