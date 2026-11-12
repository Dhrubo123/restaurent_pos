<?php

use App\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make(
                    'validation_failed',
                    'The submitted data is invalid.',
                    422,
                    $exception->errors(),
                );
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make('unauthenticated', 'Authentication is required.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make('forbidden', 'You are not allowed to perform this action.', 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make('not_found', 'The requested resource was not found.', 404);
            }
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make('csrf_token_mismatch', 'Your session expired. Refresh and try again.', 419);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                409 => 'conflict',
                419 => 'csrf_token_mismatch',
                429 => 'rate_limited',
                default => 'http_error',
            };
            $message = match (true) {
                $status === 403 => 'You are not allowed to perform this action.',
                $status === 404 => 'The requested resource was not found.',
                $status === 419 => 'Your session expired. Refresh and try again.',
                $status >= 500 || $exception->getMessage() === '' => 'The request could not be completed.',
                default => $exception->getMessage(),
            };

            $response = ApiErrorResponse::make($code, $message, $status);
            $response->headers->add($exception->getHeaders());

            return $response;
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make('server_error', 'An unexpected error occurred.', 500);
            }
        });

    })->create();
