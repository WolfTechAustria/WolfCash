<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Eine Kartenzahlung über Stripe Terminal (Tap to Pay) von der
     * Reservierung bis zur Abbuchung. Die zu bezahlenden Positionen
     * und der Betrag werden beim Start serverseitig festgehalten,
     * damit die App nur noch die Karte einliest.
     */
    public function up(): void
    {
        Schema::create('card_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('mode', 16);
            $table->json('selection')->nullable();
            $table->json('invoice_recipient')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);

            $table->string('status', 32)->default('pending');

            $table->string('stripe_payment_intent_id')
                ->nullable()
                ->unique();

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('device_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('failure_message')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_payments');
    }
};
