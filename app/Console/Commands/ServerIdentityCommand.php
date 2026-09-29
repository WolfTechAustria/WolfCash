<?php

namespace App\Console\Commands;

use App\Services\ServerIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ServerIdentityCommand extends Command
{
    protected $signature = 'wolfcash:server-identity
        {--force : Bestehende Identität ersetzen (alle Apps lernen den neuen Schlüssel beim nächsten Online-Start)}
        {--avahi : Avahi-Service-Datei für /etc/avahi/services/wolfcash.service ausgeben}';

    protected $description = 'Server-Identität für die Autodiscovery der Mobile-App erzeugen bzw. anzeigen';

    public function handle(ServerIdentity $identity): int
    {
        if ($this->option('avahi')) {
            return $this->printAvahiService($identity);
        }

        if ($identity->isConfigured() && ! $this->option('force')) {
            $this->info('Server-Identität ist bereits eingerichtet.');
            $this->line('Server-ID:  '.$identity->serverId());
            $this->line('Public Key: '.$identity->publicKey());

            return self::SUCCESS;
        }

        $serverId = (string) Str::uuid();
        $keyPair = ServerIdentity::generateKeyPair();

        if (! $this->writeEnv([
            'WOLFCASH_SERVER_ID' => $serverId,
            'WOLFCASH_SERVER_SECRET_KEY' => $keyPair['secret_key'],
        ])) {
            return self::FAILURE;
        }

        $this->info('Server-Identität wurde in .env geschrieben.');
        $this->line('Server-ID:  '.$serverId);
        $this->line('Public Key: '.$keyPair['public_key']);
        $this->warn('Bei gecachter Konfiguration: php artisan config:cache ausführen.');

        return self::SUCCESS;
    }

    private function printAvahiService(ServerIdentity $identity): int
    {
        if (! $identity->isConfigured()) {
            $this->error('Zuerst die Identität erzeugen: php artisan wolfcash:server-identity');

            return self::FAILURE;
        }

        $name = e($identity->name());
        $type = e(config('discovery.mdns_service_type'));
        $port = (int) config('discovery.mdns_port');
        $scheme = e(config('discovery.mdns_scheme'));
        $serverId = e($identity->serverId());

        $this->line(<<<XML
<?xml version="1.0" standalone='no'?>
<!DOCTYPE service-group SYSTEM "avahi-service.dtd">
<service-group>
  <name replace-wildcards="yes">{$name} (%h)</name>
  <service protocol="ipv4">
    <type>{$type}</type>
    <port>{$port}</port>
    <txt-record>id={$serverId}</txt-record>
    <txt-record>scheme={$scheme}</txt-record>
    <txt-record>path=/api</txt-record>
    <txt-record>v=1</txt-record>
  </service>
</service-group>
XML);

        return self::SUCCESS;
    }

    /**
     * Setzt bzw. ersetzt Einträge in der .env-Datei.
     *
     * @param  array<string, string>  $values
     */
    private function writeEnv(array $values): bool
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            $this->error('.env ist nicht beschreibbar: '.$path);

            foreach ($values as $key => $value) {
                $this->line($key.'='.$value);
            }

            return false;
        }

        $contents = file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        file_put_contents($path, $contents);

        return true;
    }
}
