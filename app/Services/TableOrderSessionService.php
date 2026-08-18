<?php

namespace App\Services;

use App\Models\Table;
use App\Models\TableOrderSession;
use Illuminate\Support\Str;

class TableOrderSessionService
{
    /**
     * Erstellt einen neuen öffentlichen Link für einen Tisch.
     *
     * Der Klartext-Token wird nur beim Erzeugen zurückgegeben.
     * In der DB wird ausschließlich der Hash gespeichert.
     *
     * @return array{
     *     session: TableOrderSession,
     *     token: string,
     *     url: string
     * }
     */
    public function create(
        Table $table
    ): array {
        /*
         * Alte Links dieses Tisches deaktivieren.
         */
        TableOrderSession::query()
            ->where(
                'table_id',
                $table->id
            )
            ->where(
                'active',
                true
            )
            ->update([
                'active' => false,
            ]);

        $token =
            Str::random(64);

        $session =
            TableOrderSession::create([
                'table_id' =>
                    $table->id,

                'token_hash' =>
                    hash(
                        'sha256',
                        $token
                    ),

                'active' =>
                    true,

                /*
                 * Vorerst dauerhaft gültig.
                 * Später können wir die
                 * Aktivierung pro Betrieb/Tag
                 * erweitern.
                 */
                'expires_at' =>
                    null,
            ]);

        $baseUrl = rtrim(
            config(
                'self_order.base_url'
            ),
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
