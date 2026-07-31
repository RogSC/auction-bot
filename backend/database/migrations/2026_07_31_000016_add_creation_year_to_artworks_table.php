<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('creation_year')->nullable()->after('artist_name');
        });

        DB::statement('ALTER TABLE artworks ADD CONSTRAINT artworks_creation_year_check CHECK (creation_year IS NULL OR creation_year BETWEEN 1000 AND 9999)');
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropColumn('creation_year');
        });
    }
};
