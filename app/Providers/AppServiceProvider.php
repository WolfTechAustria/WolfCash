<?php

namespace App\Providers;

use App\Http\Middleware\EnsureFloorDevice;
use App\Models\ActivityLog;
use App\Printing\EscPosNetworkTransport;
use App\Printing\PrintTransport;
use App\Printing\SimulationPrintTransport;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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
        /*
         * Geräteprüfung nicht nur beim Seitenaufruf, sondern auch
         * bei jedem Livewire-Request der Kassenoberflächen.
         */
        Livewire::addPersistentMiddleware([
            EnsureFloorDevice::class,
        ]);

        Event::listen(function (Login $event): void {
            app(ActivityLogger::class)->log(
                ActivityLog::LOGIN,
                "Anmeldung {$event->user->name}",
                userId: $event->user->getAuthIdentifier(),
            );
        });

        Event::listen(function (Failed $event): void {
            $email = (string) ($event->credentials['email'] ?? '');

            app(ActivityLogger::class)->log(
                ActivityLog::LOGIN_FAILED,
                "Fehlgeschlagene Anmeldung für {$email}",
                properties: ['email' => $email],
            );
        });

        Event::listen(function (Logout $event): void {
            if (! $event->user) {
                return;
            }

            app(ActivityLogger::class)->log(
                ActivityLog::LOGOUT,
                "Abmeldung {$event->user->name}",
                userId: $event->user->getAuthIdentifier(),
            );
        });
    }
}
