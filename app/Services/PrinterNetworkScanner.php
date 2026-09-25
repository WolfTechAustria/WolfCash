<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Sucht ESC/POS-Netzwerkdrucker in einem IP-Bereich, indem geprüft
 * wird, ob ein Host auf dem angegebenen Port (Standard 9100) eine
 * TCP-Verbindung annimmt. Reines PHP (stream_socket_client im
 * asynchronen Modus + stream_select), keine externe Dependency.
 */
class PrinterNetworkScanner
{
    public const MAX_HOSTS = 1024;

    private const BATCH_SIZE = 32;

    private const CONNECT_TIMEOUT_SECONDS = 0.3;

    /**
     * @return array<int, string> Liste der Host-IPs im Bereich (Netz-/Broadcast-Adresse ausgenommen bei Präfix < 31)
     */
    public function hostsInCidr(string $cidr): array
    {
        if (! preg_match('#^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/(\d{1,2})$#', trim($cidr), $matches)) {
            throw new InvalidArgumentException(
                'Ungültige CIDR-Notation, z. B. 192.168.1.0/24 erwartet.'
            );
        }

        $baseIp = $matches[1];
        $prefix = (int) $matches[2];

        if ($prefix < 1 || $prefix > 32) {
            throw new InvalidArgumentException('Der Präfix muss zwischen 1 und 32 liegen.');
        }

        $baseLong = ip2long($baseIp);

        if ($baseLong === false) {
            throw new InvalidArgumentException('Ungültige IP-Adresse in der CIDR-Angabe.');
        }

        $hostBits = 32 - $prefix;
        $hostCount = 2 ** $hostBits;

        if ($hostCount > self::MAX_HOSTS) {
            throw new InvalidArgumentException(
                'Der Bereich ist zu groß (maximal '.self::MAX_HOSTS.' Adressen, z. B. ein /22 oder kleiner).'
            );
        }

        $networkLong = $baseLong & (-1 << $hostBits);

        $hosts = [];

        /*
         * /31 und /32 haben keine reservierte Netz-/Broadcast-Adresse.
         */
        $start = $hostBits <= 1 ? 0 : 1;
        $end = $hostBits <= 1 ? $hostCount - 1 : $hostCount - 2;

        for ($i = $start; $i <= $end; $i++) {
            $hosts[] = long2ip($networkLong + $i);
        }

        return $hosts;
    }

    /**
     * Scannt die übergebenen Hosts auf dem angegebenen Port in Batches.
     * Ruft nach jedem Batch $onProgress(int $scannedCount, array $newlyFoundResults) auf,
     * damit der Aufrufer den Fortschritt persistieren kann.
     *
     * @param array<int, string> $hosts
     * @param callable(int, array<int, array{ip: string, port: int, response_time_ms: int}>): void $onProgress
     */
    public function scan(array $hosts, int $port, callable $onProgress): void
    {
        $scanned = 0;

        foreach (array_chunk($hosts, self::BATCH_SIZE) as $batch) {
            $found = $this->scanBatch($batch, $port);

            $scanned += count($batch);

            $onProgress($scanned, $found);
        }
    }

    /**
     * @param array<int, string> $hosts
     * @return array<int, array{ip: string, port: int, response_time_ms: int}>
     */
    private function scanBatch(array $hosts, int $port): array
    {
        $sockets = [];
        $startedAt = [];

        foreach ($hosts as $ip) {
            $socket = @stream_socket_client(
                "tcp://{$ip}:{$port}",
                $errno,
                $errstr,
                self::CONNECT_TIMEOUT_SECONDS,
                STREAM_CLIENT_ASYNC_CONNECT
            );

            if ($socket === false) {
                continue;
            }

            $sockets[$ip] = $socket;
            $startedAt[$ip] = microtime(true);
        }

        $found = [];
        $deadline = microtime(true) + self::CONNECT_TIMEOUT_SECONDS;

        while ($sockets !== [] && microtime(true) < $deadline) {
            $write = array_values($sockets);
            $read = [];
            $except = [];

            $changed = @stream_select($read, $write, $except, 0, 100000);

            if ($changed === false) {
                break;
            }

            foreach ($write as $socket) {
                $ip = array_search($socket, $sockets, true);

                if ($ip === false) {
                    continue;
                }

                /*
                 * Der Socket ist beschreibbar, sobald der asynchrone Connect
                 * abgeschlossen ist (Erfolg oder Fehler). Nur bei Erfolg lässt
                 * sich der Peer-Name auslesen.
                 */
                $connected = stream_socket_get_name($socket, true) !== false;

                if ($connected) {
                    $found[] = [
                        'ip' => $ip,
                        'port' => $port,
                        'response_time_ms' => (int) round((microtime(true) - $startedAt[$ip]) * 1000),
                    ];
                }

                fclose($socket);
                unset($sockets[$ip]);
            }
        }

        foreach ($sockets as $socket) {
            fclose($socket);
        }

        return $found;
    }
}
