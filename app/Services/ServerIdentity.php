<?php

namespace App\Services;

use RuntimeException;

/**
 * Kryptografische Identität dieser Installation (Ed25519).
 *
 * Die Mobile-App prüft damit, dass ein im lokalen Netz gefundener
 * Server wirklich dieselbe Installation ist wie der öffentliche Server.
 */
class ServerIdentity
{
    public const SIGNATURE_CONTEXT = 'wolfcash-identify:v1';

    public function isConfigured(): bool
    {
        return filled(config('discovery.server_id'))
            && filled(config('discovery.secret_key'));
    }

    public function serverId(): string
    {
        return (string) config('discovery.server_id');
    }

    public function name(): string
    {
        return (string) config('discovery.server_name');
    }

    public function publicKey(): string
    {
        return base64_encode(
            sodium_crypto_sign_publickey_from_secretkey(
                $this->secretKey()
            )
        );
    }

    /**
     * Signiert die Challenge der App. Die Server-ID ist Teil der
     * signierten Nachricht, damit eine Signatur nicht für eine
     * andere Installation wiederverwendet werden kann.
     */
    public function sign(string $nonce): string
    {
        return base64_encode(
            sodium_crypto_sign_detached(
                self::message($this->serverId(), $nonce),
                $this->secretKey()
            )
        );
    }

    public static function message(
        string $serverId,
        string $nonce
    ): string {
        return self::SIGNATURE_CONTEXT
            .':'.$serverId
            .':'.$nonce;
    }

    /**
     * @return array{secret_key: string, public_key: string}
     */
    public static function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'secret_key' => base64_encode(
                sodium_crypto_sign_secretkey($keyPair)
            ),

            'public_key' => base64_encode(
                sodium_crypto_sign_publickey($keyPair)
            ),
        ];
    }

    private function secretKey(): string
    {
        $secretKey = base64_decode(
            (string) config('discovery.secret_key'),
            true
        );

        if (
            $secretKey === false
            || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
        ) {
            throw new RuntimeException(
                'WOLFCASH_SERVER_SECRET_KEY ist ungültig.'
            );
        }

        return $secretKey;
    }
}
