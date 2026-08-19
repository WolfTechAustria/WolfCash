<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('self_orders', function (Blueprint $table): void {
            $table->string('payment_method_type')
                ->nullable()
                ->after('payment_provider');

            $table->string('payment_wallet')
                ->nullable()
                ->after('payment_method_type');

            $table->string('provider_payment_intent_id')
                ->nullable()
                ->after('provider_payment_id');

            $table->index('provider_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('self_orders', function (Blueprint $table): void {
            $table->dropIndex([
                'provider_payment_intent_id',
            ]);

            $table->dropColumn([
                'payment_method_type',
                'payment_wallet',
                'provider_payment_intent_id',
            ]);
        });
    }
};
