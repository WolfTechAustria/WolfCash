<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('invoice_recipient_name')->nullable();
            $table->text('invoice_recipient_address')->nullable();
            $table->string('invoice_recipient_vat_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_recipient_name',
                'invoice_recipient_address',
                'invoice_recipient_vat_id',
            ]);
        });
    }
};
