<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCaptcha;
use App\Models\PaymentRequest;
use App\Services\Audit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(function (): void {
            PaymentRequest::query()
                ->where('status', 'sent')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->chunkById(100, function ($requests): void {
                    foreach ($requests as $paymentRequest) {
                        $before = $paymentRequest->only(['status']);
                        $paymentRequest->update(['status' => 'expired']);
                        Audit::record('payment_request.expired', $paymentRequest, $before, ['status' => 'expired']);
                    }
                });
        })->name('expire-payment-links')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
        $middleware->trustHosts(at: fn () => ['^'.preg_quote((string) (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')).'$'], subdomains: false);
        $middleware->web(append: [ActiveUser::class, SecurityHeaders::class]);
        $middleware->alias(['captcha' => VerifyCaptcha::class]);
        $middleware->validateCsrfTokens(except: ['payments/webhook/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
