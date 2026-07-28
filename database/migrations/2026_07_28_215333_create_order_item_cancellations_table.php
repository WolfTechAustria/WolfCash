<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'order_item_cancellations',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('order_item_id')
                    ->constrained('order_items')
                    ->cascadeOnDelete();

                $table->unsignedInteger('quantity');

                $table->string('reason');

                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('cancelled_at');

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'order_item_cancellations'
        );
    }
};
