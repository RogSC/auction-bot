<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->restrictOnDelete();
            $table->foreignId('bid_id')->constrained()->restrictOnDelete();
            $table->foreignId('offered_to_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 32)->default('pending');
            $table->timestampTz('offered_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['auction_id', 'status']);
            $table->index(['offered_to_user_id', 'status']);
        });

        DB::statement("ALTER TABLE purchase_offers ADD CONSTRAINT purchase_offers_status_check CHECK (status IN ('pending', 'accepted', 'expired', 'declined', 'cancelled'))");
        DB::statement('ALTER TABLE purchase_offers ADD CONSTRAINT purchase_offers_amount_check CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE purchase_offers ADD CONSTRAINT purchase_offers_dates_check CHECK (expires_at > offered_at)');
        DB::statement("CREATE UNIQUE INDEX purchase_offers_one_pending_per_auction ON purchase_offers (auction_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_offers');
    }
};
