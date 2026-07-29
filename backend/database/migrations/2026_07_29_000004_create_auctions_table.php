<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('start_price_cents');
            $table->unsignedBigInteger('bid_increment_cents');
            $table->unsignedBigInteger('current_price_cents');
            $table->foreignId('current_leader_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at')->index();
            $table->unsignedInteger('extension_threshold_seconds')->default(120);
            $table->unsignedInteger('extension_duration_seconds')->default(120);
            $table->timestampTz('payment_due_at')->nullable()->index();
            $table->foreignId('auction_winner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('winning_bid_id')->nullable();
            $table->unsignedBigInteger('accepted_bid_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'starts_at']);
            $table->index(['status', 'ends_at']);
            $table->index('current_leader_id');
            $table->index('auction_winner_id');
            $table->index('buyer_id');
        });

        DB::statement("ALTER TABLE auctions ADD CONSTRAINT auctions_status_check CHECK (status IN ('draft', 'scheduled', 'active', 'awaiting_payment', 'paid', 'completed', 'cancelled', 'no_sale'))");
        DB::statement('ALTER TABLE auctions ADD CONSTRAINT auctions_prices_check CHECK (start_price_cents > 0 AND bid_increment_cents > 0 AND current_price_cents >= start_price_cents)');
        DB::statement('ALTER TABLE auctions ADD CONSTRAINT auctions_dates_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
