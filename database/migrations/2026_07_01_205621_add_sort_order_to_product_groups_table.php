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
        Schema::table('product_groups', function (Blueprint $table) {
            $table->integer('sort_order')
                ->default(0)
                ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
