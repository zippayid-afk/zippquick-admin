<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::prefix('customer')
                ->middleware(['api', 'resolve.language'])
                ->group(base_path('routes/customer.php'));

            Route::prefix('delivery_boy')
                ->middleware(['api', 'resolve.language'])
                ->group(base_path('routes/delivery_boy.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware stack configured - all installation checks disabled

        $middleware->append([
            \App\Http\Middleware\TrustProxies::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetLang::class,
        ]);

        // Payment-gateway webhooks / callbacks / redirects are server-to-server POSTs
        // (no session token) — exempt them from CSRF (Laravel 12 canonical config).
        $middleware->validateCsrfTokens(except: [
            'ipn',
            'webhook/stripe',
            'midtrans/callback',
            'cashfree/callback',
            'cashfree/redirect',
            'paytabs/callback',
            'paytabs/redirect',
        ]);

        $middleware->appendToGroup('api', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\ResolveLanguage::class,
            \App\Http\Middleware\DemoMode::class,
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'auth.customer' => \App\Http\Middleware\CustomerUserProvider::class,
            'customer.active' => \App\Http\Middleware\EnsureCustomerActive::class,
            'lang' => \App\Http\Middleware\SetLang::class,
            'resolve.language' => \App\Http\Middleware\ResolveLanguage::class,
            'store.scope' => \App\Http\Middleware\EnsureStoreScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            if ($request->is('api/*') || $request->is('customer/*') || $request->is('delivery_boy/*')) {
                return $request->expectsJson() || $request->hasHeader('Authorization');
            }

            return $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('delivery_boy/*') && !$request->expectsJson() && !$request->hasHeader('Authorization')) {
                return response(view('welcome'), 200);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('delivery_boy/*') && !$request->expectsJson() && !$request->hasHeader('Authorization')) {
                return response(view('welcome'), 200);
            }
        });
    })
    ->booted(function () {
        // Production mode - skip all initialization checks
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->create();
