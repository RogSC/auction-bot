<?php

declare(strict_types=1);

use App\Models\Release;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Illuminate\Support\Facades\DB;

function createReleaseForActivationTest(ReleaseStatus $status, mixed $startsAt): Release
{
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Release admin',
        'email' => 'activation-admin'.random_int(1, 999_999).'@example.test',
        'password' => 'password',
    ]);

    return Release::query()->create([
        'title' => 'Activation release',
        'status' => $status,
        'starts_at' => $startsAt,
        'created_by_admin_id' => $adminId,
    ]);
}

it('starts only scheduled releases whose start time has arrived', function (): void {
    $due = createReleaseForActivationTest(ReleaseStatus::Scheduled, now()->subMinute());
    $future = createReleaseForActivationTest(ReleaseStatus::Scheduled, now()->addMinute());
    $draft = createReleaseForActivationTest(ReleaseStatus::Draft, now()->subMinute());

    $this->artisan('releases:activate-scheduled')->assertSuccessful();

    expect($due->fresh()->status)->toBe(ReleaseStatus::Running)
        ->and($future->fresh()->status)->toBe(ReleaseStatus::Scheduled)
        ->and($draft->fresh()->status)->toBe(ReleaseStatus::Draft);
});
