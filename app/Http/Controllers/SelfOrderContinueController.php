<?php

namespace App\Http\Controllers;

use App\Models\TableOrderSession;
use App\Services\TableOrderContinuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SelfOrderContinueController extends Controller
{
    public function __invoke(
        Request $request,
        TableOrderContinuationService $continuationService
    ): RedirectResponse {
        $tableSessionId = $request->session()->get(
            'self_order_table_session_id'
        );

        if (! $tableSessionId) {
            abort(
                403,
                'Keine aktive Tischbestellung gefunden.'
            );
        }

        $tableSession = TableOrderSession::query()
            ->whereKey($tableSessionId)
            ->first();

        if (
            ! $tableSession
            || ! $tableSession->isUsable()
        ) {
            $request->session()->forget(
                'self_order_table_session_id'
            );

            abort(
                403,
                'Diese Tischbestellung ist nicht mehr verfügbar.'
            );
        }

        return redirect(
            $continuationService->createUrl(
                $tableSession
            )
        );
    }
}
