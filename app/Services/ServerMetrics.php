<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Liest Auslastung und Zustand des Kassenservers aus.
 *
 * CPU, RAM und Uptime kommen aus /proc und stehen daher nur auf
 * Linux (Live-Server) zur Verfügung; lokal unter Windows liefern
 * diese Werte null und das Dashboard zeigt "nicht verfügbar".
 */
class ServerMetrics
{
    public function collect(): array
    {
        return [
            'hostname' => gethostname() ?: '—',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'uptime_seconds' => $this->uptime(),
            'cpu' => $this->cpu(),
            'memory' => $this->memory(),
            'disk' => $this->disk(),
            'database' => $this->database(),
            'queue' => $this->queue(),
            'reverb' => $this->reverb(),
        ];
    }

    private function cpu(): ?array
    {
        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = @sys_getloadavg();

        if (! is_array($load)) {
            return null;
        }

        $cores = $this->cpuCores();

        return [
            'cores' => $cores,
            'load_1' => round($load[0], 2),
            'load_5' => round($load[1], 2),
            'load_15' => round($load[2], 2),
            'percent' => min(100, round($load[0] / max(1, $cores) * 100)),
        ];
    }

    private function cpuCores(): int
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');

        if (is_string($cpuinfo)) {
            $count = preg_match_all('/^processor\s*:/m', $cpuinfo);

            if ($count > 0) {
                return $count;
            }
        }

        return 1;
    }

    private function memory(): ?array
    {
        $meminfo = @file_get_contents('/proc/meminfo');

        if (! is_string($meminfo)) {
            return null;
        }

        preg_match_all('/^(\w+):\s+(\d+)\s*kB/m', $meminfo, $matches);

        $values = array_combine($matches[1], $matches[2]);

        $total = (int) ($values['MemTotal'] ?? 0) * 1024;
        $available = (int) ($values['MemAvailable'] ?? $values['MemFree'] ?? 0) * 1024;

        if ($total === 0) {
            return null;
        }

        $used = $total - $available;

        return [
            'total' => $total,
            'used' => $used,
            'percent' => round($used / $total * 100),
        ];
    }

    private function disk(): ?array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (! $total || $free === false) {
            return null;
        }

        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'percent' => round($used / $total * 100),
        ];
    }

    private function uptime(): ?int
    {
        $uptime = @file_get_contents('/proc/uptime');

        return is_string($uptime)
            ? (int) explode(' ', $uptime)[0]
            : null;
    }

    private function database(): array
    {
        $start = microtime(true);

        try {
            DB::select('select 1');

            $latency = round((microtime(true) - $start) * 1000, 1);

            $size = DB::getDriverName() === 'pgsql'
                ? (int) DB::scalar('select pg_database_size(current_database())')
                : null;

            return [
                'ok' => true,
                'driver' => DB::getDriverName(),
                'latency_ms' => $latency,
                'size' => $size,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'driver' => DB::getDriverName(),
                'latency_ms' => null,
                'size' => null,
            ];
        }
    }

    private function queue(): array
    {
        $pending = null;
        $failed = null;
        $oldestSeconds = null;

        try {
            if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
                $pending = DB::table('jobs')->count();

                $oldest = DB::table('jobs')->min('created_at');

                $oldestSeconds = $oldest
                    ? max(0, time() - (int) $oldest)
                    : null;
            }

            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return [
            'driver' => config('queue.default'),
            'pending' => $pending,
            'failed' => $failed,
            'oldest_seconds' => $oldestSeconds,
        ];
    }

    /**
     * Prüft, ob der Reverb-Server (WebSockets für Live-Updates)
     * auf seinem serverseitigen Host/Port Verbindungen annimmt.
     */
    private function reverb(): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        $host = config('broadcasting.connections.reverb.options.host') ?: '127.0.0.1';
        $port = (int) (config('broadcasting.connections.reverb.options.port') ?: 8080);

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);

        if ($socket) {
            fclose($socket);
        }

        return [
            'ok' => (bool) $socket,
            'host' => $host,
            'port' => $port,
        ];
    }
}
