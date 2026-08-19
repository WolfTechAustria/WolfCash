<?php

namespace App\Services;

use App\Models\TableOrderSession;
use Illuminate\Support\Facades\URL;

class TableOrderContinuationService
{
    public function createUrl(
        TableOrderSession $tableSession
    ): string {
        return URL::temporarySignedRoute(
            'self-order.resume',
            now()->addMinutes(10),
            [
                'tableSession' =>
                    $tableSession->id,
            ]
        );
    }
}
