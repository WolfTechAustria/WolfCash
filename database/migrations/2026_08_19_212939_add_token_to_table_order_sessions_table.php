<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_order_sessions', function (Blueprint $table): void {
            /*
             * Wird über Laravel encrypted cast verschlüsselt gespeichert.
             * TEXT verwenden, da verschlüsselte Werte deutlich länger werden.
             */
            $table->text('token')
                ->nullable()
                ->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('table_order_sessions', function (Blueprint $table): void {
            $table->dropColumn('token');
        });
    }
};
