<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'auction.payment_window_hours')->update([
            'value' => json_encode(72, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'auction.payment_window_hours')->update([
            'value' => json_encode(24, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
