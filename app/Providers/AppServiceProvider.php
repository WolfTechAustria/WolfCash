<?php

namespace App\Providers;

use App\Printing\EscPosNetworkTransport;
use App\Printing\PrintTransport;
use App\Printing\SimulationPrintTransport;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PrintTransport::class,
            function ($app) {
                return match (config('printing.driver')) {
                    'simulation' =>
                    new SimulationPrintTransport(),

                    'escpos_network' =>
                    new EscPosNetworkTransport(),

                    default =>
                    throw new RuntimeException(
                        'Unbekannter PRINT_DRIVER: '
                        .config('printing.driver')
                    ),
                };
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
