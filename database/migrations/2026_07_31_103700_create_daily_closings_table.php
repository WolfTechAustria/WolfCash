<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();

            /*
             * Ein Geschäftstag darf nur einmal abgeschlossen werden.
             */
            $table->date('business_date')->unique();

            $table->timestamp('closed_at');

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Bestellstatistik
             */
            $table->unsignedInteger('orders_total')->default(0);
            $table->unsignedInteger('orders_paid')->default(0);
            $table->unsignedInteger('orders_cancelled')->default(0);

            /*
             * Geldbeträge
             */
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('cancelled_amount', 12, 2)->default(0);
            $table->decimal('payable_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('cash_amount', 12, 2)->default(0);
            $table->decimal('card_amount', 12, 2)->default(0);

            /*
             * Vorgänge
             */
            $table->unsignedInteger('payments_count')->default(0);
            $table->unsignedInteger('cancellations_count')->default(0);
            $table->unsignedInteger('cancelled_quantity')->default(0);

            /*
             * Vollständiger Snapshot für spätere Erweiterungen.
             */
            $table->json('snapshot');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
