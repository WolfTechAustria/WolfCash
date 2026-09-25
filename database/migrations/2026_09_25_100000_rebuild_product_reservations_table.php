<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Die bisherige Tabelle war leer und ungenutzt (nur id/timestamps),
     * daher wird sie neu aufgebaut statt Spalten nachzurüsten.
     */
    public function up(): void
    {
        Schema::dropIfExists('product_reservations');

        Schema::create('product_reservations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Besitzer der Reservierung, z. B. "pos:{uuid}",
             * "self:{uuid}" oder "self-order:{id}".
             */
            $table->string('holder', 100)->index();

            $table->foreignId('self_order_id')
                ->nullable()
                ->constrained('self_orders')
                ->nullOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamp('expires_at')->index();

            $table->timestamps();

            $table->unique(['product_id', 'holder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reservations');

        Schema::create('product_reservations', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
};
