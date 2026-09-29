<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Pro Position, nicht pro Bestellung: An einem Tisch buchen im
     * Laufe des Abends oft mehrere Geräte in dieselbe Bestellung.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('device_id')
                ->nullable()
                ->after('payment_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->after('device_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['device_id', 'user_id']);
        });
    }
};
