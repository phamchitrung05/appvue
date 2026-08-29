<?php

use App\Services\BaseResponseService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer with JSON on the API routes, even when the client
        // forgets to send an `Accept: application/json` header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        /**
         * Lỗi validate (422) — lỗi frontend gặp nhiều nhất. Render qua
         * BaseResponseService để frontend luôn thấy envelope chuẩn kèm
         * code = VALIDATION_FAILED và bag chi tiết lỗi trong `errors`.
         */
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return app(BaseResponseService::class)->error(
                    config('messages.common.validation_failed'),
                    422,
                    $e->errors(),
                    'VALIDATION_FAILED'
                );
            }
        });

        /**
         * Lỗi chưa đăng nhập / phiên hết hạn (401) — dùng khi sau này
         * có auth: frontend thấy code UNAUTHENTICATED để tự chuyển trang login.
         */
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return app(BaseResponseService::class)->error(
                    config('messages.common.unauthenticated'),
                    401,
                    null,
                    'UNAUTHENTICATED'
                );
            }
        });
    })->create();
