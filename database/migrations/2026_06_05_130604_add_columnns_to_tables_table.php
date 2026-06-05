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
        Schema::table('tables', function (Blueprint $table) {
            $table->string('number')->after('id');
            $table->string('name')->nullable()->after('number');
            $table->enum('status', ['free', 'occupied', 'reserved'])->default('free')->after('name');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->string('number')->after('id');
            $table->string('name')->nullable()->after('number');
            $table->enum('status', ['free', 'occupied', 'reserved'])->default('free')->after('name');
        });
    }
};
