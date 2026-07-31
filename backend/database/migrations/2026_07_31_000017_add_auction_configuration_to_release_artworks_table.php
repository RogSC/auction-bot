<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_artworks', function (Blueprint $table): void {
            $table->unsignedBigInteger('start_price_cents')->nullable()->after('position');
            $table->unsignedBigInteger('bid_increment_cents')->nullable()->after('start_price_cents');
            $table->foreignId('auction_id')->nullable()->unique()->after('bid_increment_cents')
                ->constrained('auctions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('release_artworks', function (Blueprint $table): void {
            $table->dropForeign(['auction_id']);
            $table->dropUnique(['auction_id']);
            $table->dropColumn(['auction_id', 'bid_increment_cents', 'start_price_cents']);
        });
    }
};
