<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->timestampTz('auction_starts_at')->nullable()->after('starts_at')->index();
        });

        DB::table('releases')->whereNotNull('starts_at')->update([
            'auction_starts_at' => DB::raw('starts_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->dropColumn('auction_starts_at');
        });
    }
};
