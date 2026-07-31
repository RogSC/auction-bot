<?php

declare(strict_types=1);

use App\Models\Auction;
use Illuminate\Support\Facades\DB;

it('initializes the current price from the start price when an auction is created', function (): void {
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Admin',
        'email' => 'auction-creation@example.test',
        'password' => 'password',
    ]);
    $artworkId = DB::table('artworks')->insertGetId([
        'title' => 'Artwork',
        'description' => 'Description',
        'preview_disk' => 'local',
        'preview_path' => 'preview.jpg',
        'created_by_admin_id' => $adminId,
    ]);

    $auction = Auction::query()->create([
        'artwork_id' => $artworkId,
        'start_price_cents' => 10_000,
        'bid_increment_cents' => 1_000,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'extension_threshold_seconds' => 120,
        'extension_duration_seconds' => 120,
    ]);

    expect($auction->current_price_cents)->toBe(10_000)
        ->and($auction->current_leader_id)->toBeNull();
});
