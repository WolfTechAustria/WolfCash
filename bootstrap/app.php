<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\AuthenticateDevice;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device.approved' => \App\Http\Middleware\EnsureDeviceIsApproved::class,
            'device.auth' => AuthenticateDevice::class,
        ]);
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(
            except: [
                'stripe/webhook',
            ]
        );

        /*
         * Wird direkt per JavaScript (document.cookie) gesetzt und
         * gelesen, u. a. für die Day/Night-Umschaltung der Kellner-UI.
         * Ohne Ausnahme würde die serverseitige Entschlüsselung des
         * unverschlüsselten Client-Cookies fehlschlagen.
         */
        $middleware->encryptCookies(except: [
            'wolfcash_theme',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
