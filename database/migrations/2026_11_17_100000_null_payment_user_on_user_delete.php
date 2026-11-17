<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Benutzer sollen im Admin gelöscht werden können. payments.user_id
 * war ohne ON DELETE angelegt und hätte das Löschen jedes Benutzers
 * mit Zahlungen blockiert. Die Zahlung bleibt erhalten, nur der
 * Verweis auf den Benutzer wird geleert — wie bei allen anderen
 * Verweisen auf users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }
};
