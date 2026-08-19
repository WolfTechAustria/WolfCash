<?php

namespace App\Services;

use App\Models\Table;
use App\Models\TableOrderSession;
use Illuminate\Support\Str;

class TableOrderSessionService
{
    /**
     * Liefert die bestehende QR-Session oder erzeugt eine neue.
     *
     * @return array{
     *     session: TableOrderSession,
     *     token: string,
     *     url: string
     * }
     */
    public function getOrCreate(
        Table $table
    ): array {
        $session = TableOrderSession::query()
            ->where('table_id', $table->id)
            ->where('active', true)
            ->latest('id')
            ->first();

        /*
         * Alte Sessions aus der Zeit vor dem verschlüsselten
         * token-Feld können nicht rekonstruiert werden.
         * In diesem Fall einmal neu erzeugen.
         */
        if (
            ! $session
            || ! $session->token
        ) {
            return $this->regenerate($table);
        }

        return $this->result(
            $session,
            $session->token
        );
    }


    /**
     * Alten QR ungültig machen und neuen erzeugen.
     */
    public function regenerate(
        Table $table
    ): array {
        TableOrderSession::query()
            ->where('table_id', $table->id)
            ->where('active', true)
            ->update([
                'active' => false,
            ]);

        $token = Str::random(64);

        $session = TableOrderSession::create([
            'table_id' =>
                $table->id,

            'token_hash' =>
                hash(
                    'sha256',
                    $token
                ),

            /*
             * Wird durch encrypted cast verschlüsselt.
             */
            'token' =>
                $token,

            'active' =>
                true,

            'expires_at' =>
                null,
        ]);

        return $this->result(
            $session,
            $token
        );
    }


    private function result(
        TableOrderSession $session,
        string $token
    ): array {
        $baseUrl = rtrim(
            config('self_order.base_url'),
            '/'
        );

        return [
            'session' =>
                $session,

            'token' =>
                $token,

            'url' =>
                $baseUrl
                .'/o/'
                .$token,
        ];
    }
}
