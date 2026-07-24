<?php

namespace App\Jobs;

use App\Models\PrintOutput;
use App\Services\PrintOutputProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ProcessPrintOutput implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $printOutputId,
    ) {
        $this->onQueue('printing');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [
            5,
            15,
            30,
            60,
        ];
    }

    public function handle(
        PrintOutputProcessor $processor
    ): void {
        $output = PrintOutput::find($this->printOutputId);

        if (! $output) {
            throw new RuntimeException(
                "PrintOutput #{$this->printOutputId} wurde nicht gefunden."
            );
        }

        if ($output->status === PrintOutput::STATUS_PRINTED) {
            return;
        }

        $processor->process($output);

        $output->refresh();

        if ($output->status !== PrintOutput::STATUS_PRINTED) {
            throw new RuntimeException(
                $output->error_message
                    ?: "PrintOutput #{$output->id} konnte nicht gedruckt werden."
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $output = PrintOutput::find($this->printOutputId);

        if (! $output) {
            return;
        }

        $output->update([
            'status' => PrintOutput::STATUS_FAILED,
            'error_message' => $exception?->getMessage()
                ?? 'Der Ausdruck ist endgültig fehlgeschlagen.',
        ]);
    }
}
