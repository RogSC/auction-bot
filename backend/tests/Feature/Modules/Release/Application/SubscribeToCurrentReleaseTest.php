<?php

declare(strict_types=1);

use App\Models\Release;
use App\Models\ReleaseSubscription;
use App\Models\User;
use App\Modules\Release\Application\SubscribeToCurrentRelease;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

function createRunningReleaseForSubscriptionTest(): Release
{
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Release admin',
        'email' => 'release-admin@example.test',
        'password' => 'password',
    ]);

    return Release::query()->create([
        'title' => 'July release',
        'status' => ReleaseStatus::Running,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'created_by_admin_id' => $adminId,
    ]);
}

function createUserForReleaseSubscriptionTest(): User
{
    return User::query()->create([
        'telegram_id' => random_int(1_000_000, 9_999_999),
        'bidder_code' => 'BIDDER-'.random_int(100_000, 999_999),
    ]);
}

it('subscribes a user to the currently running release from the current moment', function (): void {
    $release = createRunningReleaseForSubscriptionTest();
    $user = createUserForReleaseSubscriptionTest();
    $beforeSubscription = now();

    $subscription = app(SubscribeToCurrentRelease::class)->handle($user);

    expect($subscription)->not->toBeNull()
        ->and($subscription->release_id)->toBe($release->id)
        ->and($subscription->user_id)->toBe($user->id)
        ->and($subscription->subscribed_at->greaterThanOrEqualTo($beforeSubscription->subSecond()))->toBeTrue()
        ->and($subscription->unsubscribed_at)->toBeNull();
});

it('does not create duplicate subscriptions when a user starts again', function (): void {
    createRunningReleaseForSubscriptionTest();
    $user = createUserForReleaseSubscriptionTest();

    app(SubscribeToCurrentRelease::class)->handle($user);
    app(SubscribeToCurrentRelease::class)->handle($user);

    expect(ReleaseSubscription::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('does not subscribe a user when no release is currently running', function (): void {
    $user = createUserForReleaseSubscriptionTest();

    expect(app(SubscribeToCurrentRelease::class)->handle($user))->toBeNull()
        ->and(ReleaseSubscription::query()->count())->toBe(0);
});

it('resubscribes from the current moment after an unsubscribe', function (): void {
    $release = createRunningReleaseForSubscriptionTest();
    $user = createUserForReleaseSubscriptionTest();
    $subscription = ReleaseSubscription::query()->create([
        'release_id' => $release->id,
        'user_id' => $user->id,
        'subscribed_at' => now()->subHour(),
        'unsubscribed_at' => now()->subMinute(),
    ]);
    $previousSubscriptionTime = $subscription->subscribed_at;

    $resubscribed = app(SubscribeToCurrentRelease::class)->handle($user);

    expect($resubscribed->unsubscribed_at)->toBeNull()
        ->and($resubscribed->subscribed_at->greaterThan($previousSubscriptionTime))->toBeTrue();
});
