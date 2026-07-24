<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_outputs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('print_job_id')
                ->constrained('print_jobs')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->nullOnDelete();

            $table->foreignId('printer_id')
                ->constrained('printers')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity')
                ->default(1);

            $table->string('type')
                ->default('production');

            $table->string('status')
                ->default('pending');

            $table->json('payload');

            $table->timestamp('printed_at')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'printer_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_outputs');
    }
};
