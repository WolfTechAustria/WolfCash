<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use App\Services\PrintJobProcessor;
use Illuminate\Console\Command;

class ProcessPrintJobs extends Command
{
    protected $signature = 'print:process
                            {--limit=20 : Maximale Anzahl Jobs}';

    protected $description =
        'Verarbeitet offene ESC/POS-Druckjobs';

    public function handle(
        PrintJobProcessor $processor
    ): int {
        $limit = max(
            1,
            (int) $this->option('limit')
        );

        $jobs = PrintJob::query()
            ->where('status', PrintJob::STATUS_PENDING)
            ->whereNotNull('printer_id')
            ->oldest()
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('Keine offenen Druckjobs.');

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $this->line(
                "Verarbeite PrintJob #{$job->id} ..."
            );

            $processor->process($job);

            $job->refresh();

            if ($job->status === PrintJob::STATUS_PRINTED) {
                $this->info(
                    "PrintJob #{$job->id} erfolgreich."
                );
            } else {
                $this->error(
                    "PrintJob #{$job->id} fehlgeschlagen: "
                    .$job->error_message
                );
            }
        }

        return self::SUCCESS;
    }
}
