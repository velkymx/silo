<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Turn "POST data too large" (upload exceeds PHP's post_max_size) into a
        // friendly flash instead of a 500. Raise post_max_size/upload_max_filesize
        // in php.ini (the Docker image already sets 256M) to allow bigger uploads.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            $message = 'That upload is larger than the server allows. Try a smaller file or raise the server upload limit.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->with('error', $message);
        });
    })->create();
