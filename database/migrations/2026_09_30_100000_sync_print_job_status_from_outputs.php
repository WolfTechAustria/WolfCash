<?php

use App\Models\PrintJob;
use App\Models\PrintOutput;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /*
     * Einmaliger Abgleich: Belege und Bons mit Auslöser "pro Position"
     * blieben bisher auf "pending", obwohl ihre Einzelausdrucke längst
     * gedruckt waren.
     */
    public function up(): void
    {
        PrintJob::query()
            ->whereIn('status', [
                PrintJob::STATUS_PENDING,
                PrintJob::STATUS_PRINTING,
            ])
            ->whereHas('outputs', fn ($query) => $query->where(
                'type',
                '!=',
                PrintOutput::TYPE_CANCELLATION
            ))
            ->each(fn (PrintJob $job) => $job->syncStatusFromOutputs());
    }

    public function down(): void
    {
        //
    }
};
