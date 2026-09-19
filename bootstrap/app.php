<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * PHP corta la petición ANTES de entrar a Laravel cuando el archivo
         * supera `post_max_size` (el middleware ValidatePostSize responde 413).
         * En el panel de tutoriales se vuelve a él con `?error=size` para que la
         * pantalla explique el motivo, en lugar de mostrar un error genérico.
         */
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('gestion-tutoriales*')) {
                return redirect('/gestion-tutoriales?error=size');
            }

            return null;
        });
    })->create();
