<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 32)->default('active');
            $table->timestampTz('placed_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['auction_id', 'status', 'amount_cents']);
            $table->index(['user_id', 'placed_at']);
        });

        DB::statement("ALTER TABLE bids ADD CONSTRAINT bids_status_check CHECK (status IN ('active', 'outbid', 'winning', 'cancelled', 'disqualified'))");
        DB::statement('ALTER TABLE bids ADD CONSTRAINT bids_amount_check CHECK (amount_cents > 0)');
        DB::statement("CREATE UNIQUE INDEX bids_one_winning_bid_per_auction ON bids (auction_id) WHERE status = 'winning'");
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
