<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_order_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('self_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            /*
             * Snapshot zum Zeitpunkt der Bestellung.
             */
            $table->string('name');

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 10, 2);

            $table->text('note')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_order_items');
    }
};
