<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;

class ProcessPrintJobs extends Command
{
    protected $signature = 'print:process';

    protected $description = 'Process pending print jobs';

    public function handle(): int
    {
        $jobs = PrintJob::where('status', PrintJob::STATUS_PENDING)
            ->oldest()
            ->get();

        foreach ($jobs as $job) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTING,
            ]);

            // Simulation: später kommt hier echter Drucker-Code hin.
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'printed_at' => now(),
                'error_message' => null,
            ]);

            $this->info("Print job {$job->id} marked as printed.");
        }

        return self::SUCCESS;
    }
}
