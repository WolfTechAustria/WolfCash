<?php

namespace App\Http\Controllers;

use App\Models\SelfOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SelfOrderPaymentController extends Controller
{
    public function success(
        Request $request,
        SelfOrder $selfOrder
    ): View {
        return view(
            'self-order.payment-success',
            [
                'selfOrder' =>
                    $selfOrder,
            ]
        );
    }

    public function cancel(
        SelfOrder $selfOrder
    ): View {
        return view(
            'self-order.payment-cancel',
            [
                'selfOrder' =>
                    $selfOrder,
            ]
        );
    }
}
