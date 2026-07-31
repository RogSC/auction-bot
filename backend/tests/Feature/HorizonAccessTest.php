<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Laravel\Horizon\Horizon;

it('allows Horizon access only to an active administrator authenticated with the admin guard', function (): void {
    $activeAdmin = Admin::query()->create([
        'name' => 'Active admin',
        'email' => 'horizon-active@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);
    $inactiveAdmin = Admin::query()->create([
        'name' => 'Inactive admin',
        'email' => 'horizon-inactive@example.test',
        'password' => 'password',
        'is_active' => false,
    ]);

    Auth::guard('admin')->setUser($activeAdmin);
    expect(Horizon::check(request()))->toBeTrue();

    Auth::guard('admin')->setUser($inactiveAdmin);
    expect(Horizon::check(request()))->toBeFalse();

    Auth::guard('admin')->forgetUser();
    expect(Horizon::check(request()))->toBeFalse();
});
