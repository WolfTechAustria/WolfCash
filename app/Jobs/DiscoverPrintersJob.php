<?php

namespace App\Jobs;

use App\Models\Printer;
use App\Models\PrinterDiscoveryScan;
use App\Services\PrinterNetworkScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DiscoverPrintersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $scanId,
    ) {
        $this->onQueue('printing');
    }

    public function handle(PrinterNetworkScanner $scanner): void
    {
        $scan = PrinterDiscoveryScan::find($this->scanId);

        if (! $scan) {
            return;
        }

        try {
            $hosts = $scanner->hostsInCidr($scan->subnet_cidr);

            $scan->update([
                'status' => PrinterDiscoveryScan::STATUS_RUNNING,
                'total_hosts' => count($hosts),
                'scanned_hosts' => 0,
                'results' => [],
                'started_at' => now(),
            ]);

            $knownIps = Printer::query()
                ->whereNotNull('ip_address')
                ->pluck('ip_address')
                ->all();

            $results = [];

            $scanner->scan(
                $hosts,
                $scan->port,
                function (int $scanned, array $newlyFound) use ($scan, &$results, $knownIps): void {
                    foreach ($newlyFound as $result) {
                        $result['already_known'] = in_array($result['ip'], $knownIps, true);
                        $results[] = $result;
                    }

                    $scan->update([
                        'scanned_hosts' => $scanned,
                        'results' => $results,
                    ]);
                }
            );

            $scan->update([
                'status' => PrinterDiscoveryScan::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $scan->update([
                'status' => PrinterDiscoveryScan::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
