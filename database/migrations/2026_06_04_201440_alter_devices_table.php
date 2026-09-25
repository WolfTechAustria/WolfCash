<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'uuid')) {
                /*
                 * Der zugehörige Unique-Index muss vor der Spalte
                 * entfernt werden, sonst schlägt dropColumn() unter
                 * SQLite fehl (Postgres räumt das automatisch auf).
                 */
                $table->dropUnique('devices_uuid_unique');
                $table->dropColumn('uuid');
            }
        });

        Schema::table('devices', function (Blueprint $table) {

            $table->uuid('uuid')->unique();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {

            $table->dropColumn('uuid')->unique();

        });
    }
};
