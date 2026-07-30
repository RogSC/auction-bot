<?php

declare(strict_types=1);

use App\Models\Artwork;
use App\Models\Release;
use App\Models\ReleaseDelivery;
use App\Models\ReleaseEvent;
use App\Models\ReleaseSubscription;
use App\Models\User;
use App\Modules\Release\Application\DeliverReleaseEvent;
use App\Modules\Release\Domain\Enums\ReleaseDeliveryStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseNotificationMode;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function createReleaseForDeliveryTest(): Release
{
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Release admin',
        'email' => 'delivery-admin@example.test',
        'password' => 'password',
    ]);

    return Release::query()->create([
        'title' => 'Delivery release',
        'status' => ReleaseStatus::Running,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'created_by_admin_id' => $adminId,
    ]);
}

function createArtworkForDeliveryTest(int $adminId): Artwork
{
    Storage::disk('local')->put('artwork-previews/test.jpg', 'image-content');

    return Artwork::query()->create([
        'title' => 'Untitled',
        'description' => 'A work.',
        'preview_disk' => 'local',
        'preview_path' => 'artwork-previews/test.jpg',
        'created_by_admin_id' => $adminId,
    ]);
}

function createSubscriberForDeliveryTest(Release $release, mixed $subscribedAt): User
{
    $user = User::query()->create([
        'telegram_id' => random_int(1_000_000, 9_999_999),
        'bidder_code' => 'BIDDER-'.random_int(100_000, 999_999),
    ]);
    ReleaseSubscription::query()->create([
        'release_id' => $release->id,
        'user_id' => $user->id,
        'subscribed_at' => $subscribedAt,
    ]);

    return $user;
}

function configureTelegramForReleaseDeliveryTest(): void
{
    Config::set('telegram.bot_token', 'test-token');
    Config::set('telegram.api_base_url', 'https://telegram.test');
}

it('does not send a past artwork event to a subscriber who joined later', function (): void {
    Storage::fake('local');
    configureTelegramForReleaseDeliveryTest();
    Http::fake(['https://telegram.test/*' => Http::response(['ok' => true, 'result' => ['message_id' => 101]])]);
    $release = createReleaseForDeliveryTest();
    $adminId = $release->created_by_admin_id;
    $artwork = createArtworkForDeliveryTest($adminId);
    $scheduledAt = now()->subMinute();
    $event = ReleaseEvent::query()->create([
        'release_id' => $release->id,
        'artwork_id' => $artwork->id,
        'sequence' => 1,
        'type' => ReleaseEventType::DeliverArtwork,
        'scheduled_at' => $scheduledAt,
    ]);
    $earlySubscriber = createSubscriberForDeliveryTest($release, now()->subMinutes(2));
    $lateSubscriber = createSubscriberForDeliveryTest($release, now());

    app(DeliverReleaseEvent::class)->handle($event->id);

    expect(ReleaseDelivery::query()->where('release_event_id', $event->id)->pluck('user_id')->all())->toBe([$earlySubscriber->id])
        ->and($event->fresh()->status)->toBe(ReleaseEventStatus::Completed)
        ->and(ReleaseDelivery::query()->where('user_id', $lateSubscriber->id)->count())->toBe(0);
    Http::assertSentCount(1);
});

it('sends an explanation quietly as a reply to that user artwork message', function (): void {
    Storage::fake('local');
    configureTelegramForReleaseDeliveryTest();
    Http::fakeSequence()
        ->push(['ok' => true, 'result' => ['message_id' => 501]])
        ->push(['ok' => true, 'result' => ['message_id' => 502]]);
    $release = createReleaseForDeliveryTest();
    $artwork = createArtworkForDeliveryTest($release->created_by_admin_id);
    $user = createSubscriberForDeliveryTest($release, now()->subHour());
    $artworkEvent = ReleaseEvent::query()->create([
        'release_id' => $release->id,
        'artwork_id' => $artwork->id,
        'sequence' => 1,
        'type' => ReleaseEventType::DeliverArtwork,
        'scheduled_at' => now()->subMinutes(10),
    ]);
    $explanationEvent = ReleaseEvent::query()->create([
        'release_id' => $release->id,
        'artwork_id' => $artwork->id,
        'sequence' => 2,
        'type' => ReleaseEventType::DeliverExplanation,
        'notification_mode' => ReleaseNotificationMode::Silent,
        'payload' => ['text' => 'Экспликация работы.'],
        'scheduled_at' => now()->subMinute(),
    ]);

    app(DeliverReleaseEvent::class)->handle($artworkEvent->id);
    app(DeliverReleaseEvent::class)->handle($explanationEvent->id);

    $explanationDelivery = ReleaseDelivery::query()->where('release_event_id', $explanationEvent->id)->firstOrFail();
    expect($explanationDelivery->status)->toBe(ReleaseDeliveryStatus::Sent)
        ->and($explanationDelivery->telegram_message_id)->toBe(502);
    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/sendMessage')
            && $request['disable_notification'] === true
            && $request['reply_parameters']['message_id'] === 501;
    });

    expect($user->id)->toBeGreaterThan(0);
});
