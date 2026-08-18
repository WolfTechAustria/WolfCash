<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('table_order_session_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('table_id')
                ->constrained('tables')
                ->restrictOnDelete();

            /*
             * Wird erst gesetzt, nachdem die Zahlung
             * erfolgreich war und die echte Order erzeugt wurde.
             */
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->string('status')
                ->default('draft');

            $table->decimal('amount', 10, 2)
                ->default(0);

            $table->string('currency', 3)
                ->default('EUR');

            $table->string('payment_provider')
                ->nullable();

            $table->string('provider_payment_id')
                ->nullable()
                ->unique();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamp('submitted_at')
                ->nullable();

            $table->timestamp('expires_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'table_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_orders');
    }
};
