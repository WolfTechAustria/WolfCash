<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use App\Services\PrintJobProcessor;
use Illuminate\Console\Command;

class ProcessPrintJobs extends Command
{
    protected $signature = 'print:process';

    protected $description = 'Process pending print jobs';

    public function handle(PrintJobProcessor $processor): int
    {
        $limit = max(1,(int)$this->option('limit'));

        $jobs = PrintJob::query()
            ->where('status', PrintJob::STATUS_PENDING)
            ->where('ready_to_print', true)
            ->whereNotNull('printer_id')
            ->oldest()
            ->limit($limit)
            ->get();

        if($jobs->isEmpty()) {
            $this->info('keine offenen Druckjobs');
            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $this->line('Verarbeite PrintJob #{$job->id} ...');
            try{
                $processor->process($job);

                $job->refresh();
                if($job->status === PrintJob:: STATUS_PENDING) {
                    $this->info('Druckjob #{$job->id} erfolgreich abgeschlossen');
                } else {
                    $this->error('PrintJob #{$job->id} fehlgeschlagen: '.$job->error_message);
                }
            } catch (\Throwable $exception) {
                $this->error('PrintJob #{$job->id} fehlgeschlagen: '.$exception->getMessage());
            }

        }

        return self::SUCCESS;
    }
}
