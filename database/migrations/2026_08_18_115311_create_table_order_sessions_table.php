<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_order_sessions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('table_id')
                ->constrained('tables')
                ->cascadeOnDelete();

            /*
             * Im QR-Code steht später der echte zufällige Token.
             * In der DB speichern wir nur dessen SHA-256-Hash.
             */
            $table->string('token_hash', 64)
                ->unique();

            $table->boolean('active')
                ->default(true);

            $table->timestamp('expires_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'table_id',
                'active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_sessions');
    }
};
