<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Nur anhängen, nie ändern: Benutzer- und Gerätename werden als
     * Snapshot gespeichert, damit Einträge auch nach Umbenennen oder
     * Löschen (System-Reset) lesbar bleiben.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64)->index();
            $table->string('description');
            $table->nullableMorphs('subject');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('user_name')->nullable();

            $table->foreignId('device_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('device_name')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
