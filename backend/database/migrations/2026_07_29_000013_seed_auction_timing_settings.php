<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array Keys = [
        'auction.anti_sniping_threshold_seconds',
        'auction.anti_sniping_extension_seconds',
        'auction.payment_window_hours',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('settings')->insert([
            [
                'key' => 'auction.anti_sniping_threshold_seconds',
                'value' => json_encode(120, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'auction.anti_sniping_extension_seconds',
                'value' => json_encode(120, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'auction.payment_window_hours',
                'value' => json_encode(24, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', self::Keys)->delete();
    }
};
