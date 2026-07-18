<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->string('connection_type')
                ->default('network')
                ->after('name');

            $table->string('host')
                ->nullable()
                ->after('connection_type');

            $table->unsignedInteger('port')
                ->default(9100)
                ->after('host');

            $table->unsignedInteger('characters_per_line')
                ->default(42)
                ->after('port');

            $table->boolean('is_enabled')
                ->default(true)
                ->after('characters_per_line');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'host',
                'port',
                'characters_per_line',
                'is_enabled',
            ]);
        });
    }
};
