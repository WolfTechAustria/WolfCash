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
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type')->default('production');


            $table->json('payload');

            $table->text('error_message')->nullable();

        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            //
        });
    }
};
