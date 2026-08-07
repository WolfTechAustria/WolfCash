<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('payment_id')
                ->nullable()
                ->after('order_id')
                ->constrained('payments')
                ->nullOnDelete();

            /*
             * Pro Zahlung darf nur ein ursprünglicher Belegjob
             * entstehen. Nachdrucke werden später als zusätzliche
             * PrintOutputs desselben Jobs erzeugt.
             */
            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropUnique([
                'payment_id',
            ]);

            $table->dropConstrainedForeignId(
                'payment_id'
            );
        });
    }
};
