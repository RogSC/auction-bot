<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('id');
            $table->string('bidder_code', 32)->nullable()->unique()->after('telegram_id');
            $table->string('first_name')->nullable()->after('bidder_code');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('username')->nullable()->after('last_name');
            $table->string('accepted_terms_version', 32)->nullable()->after('remember_token');
            $table->timestampTz('accepted_terms_at')->nullable()->after('accepted_terms_version');
        });

        DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_id']);
            $table->dropUnique(['bidder_code']);
            $table->dropColumn([
                'telegram_id',
                'bidder_code',
                'first_name',
                'last_name',
                'username',
                'accepted_terms_version',
                'accepted_terms_at',
            ]);
        });
    }
};
