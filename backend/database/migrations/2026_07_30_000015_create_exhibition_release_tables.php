<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('status', 16)->default('draft')->index();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable()->index();
            $table->unsignedInteger('timeline_scale_basis_points')->default(10000);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE releases ADD CONSTRAINT releases_status_check CHECK (status IN ('draft', 'scheduled', 'running', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE releases ADD CONSTRAINT releases_timeline_scale_check CHECK (timeline_scale_basis_points > 0)');

        Schema::create('release_artworks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artwork_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->timestampsTz();

            $table->unique(['release_id', 'artwork_id']);
            $table->unique(['release_id', 'position']);
        });

        Schema::create('release_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artwork_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('auction_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type', 32);
            $table->string('notification_mode', 16)->default('loud');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('scheduled_at')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['release_id', 'sequence']);
            $table->index(['status', 'scheduled_at']);
        });

        DB::statement("ALTER TABLE release_events ADD CONSTRAINT release_events_type_check CHECK (type IN ('deliver_artwork', 'deliver_explanation', 'delete_artwork_message', 'send_catalog', 'activate_auction'))");
        DB::statement("ALTER TABLE release_events ADD CONSTRAINT release_events_notification_mode_check CHECK (notification_mode IN ('loud', 'silent'))");
        DB::statement("ALTER TABLE release_events ADD CONSTRAINT release_events_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'cancelled'))");

        Schema::create('release_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('subscribed_at');
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['release_id', 'user_id']);
            $table->index(['release_id', 'subscribed_at']);
        });

        Schema::create('release_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['release_event_id', 'user_id']);
            $table->index(['user_id', 'telegram_message_id']);
        });

        DB::statement("ALTER TABLE release_deliveries ADD CONSTRAINT release_deliveries_status_check CHECK (status IN ('pending', 'sent', 'deleted', 'failed'))");

        Schema::table('artworks', function (Blueprint $table): void {
            $table->string('artist_name')->nullable()->after('title');
            $table->text('ownership_terms')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropColumn(['artist_name', 'ownership_terms']);
        });

        Schema::dropIfExists('release_deliveries');
        Schema::dropIfExists('release_subscriptions');
        Schema::dropIfExists('release_events');
        Schema::dropIfExists('release_artworks');
        Schema::dropIfExists('releases');
    }
};
