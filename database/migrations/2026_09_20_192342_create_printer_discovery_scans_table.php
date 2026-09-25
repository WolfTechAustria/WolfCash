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
        Schema::create('printer_discovery_scans', function (Blueprint $table) {
            $table->id();
            $table->string('subnet_cidr');
            $table->unsignedInteger('port')->default(9100);
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_hosts')->default(0);
            $table->unsignedInteger('scanned_hosts')->default(0);
            $table->json('results')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_discovery_scans');
    }
};
