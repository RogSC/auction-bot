<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->foreign('winning_bid_id')->references('id')->on('bids')->restrictOnDelete();
            $table->foreign('accepted_bid_id')->references('id')->on('bids')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['winning_bid_id']);
            $table->dropForeign(['accepted_bid_id']);
        });
    }
};
