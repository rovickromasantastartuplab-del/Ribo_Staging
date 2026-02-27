<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ShareGlobalSettings;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\DemoModeMiddleware;
use App\Http\Middleware\CheckPlanFeature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            CheckInstallation::class,
            HandleAppearance::class,
            ShareGlobalSettings::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            DemoModeMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'landing.enabled' => \App\Http\Middleware\CheckLandingPageEnabled::class,
            'verified' => App\Http\Middleware\EnsureEmailIsVerified::class,
            'plan.access' => \App\Http\Middleware\CheckPlanAccess::class,
            'plan.feature' => CheckPlanFeature::class,
            'onboarding' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
        ]);

        $middleware->validateCsrfTokens(
            except: [
                'install/*',
                'update/*',
                'cashfree/create-session',
                'cashfree/webhook',
                'ozow/create-payment',
                'payments/easebuzz/success',
                'payments/aamarpay/success',
                'payments/aamarpay/callback',
                'payments/tap/success',
                'payments/tap/callback',
                'payments/benefit/success',
                'payments/benefit/callback',
                'payments/paytabs/callback',
                'payments/iyzipay/success',
                'payments/iyzipay/callback',
                'payments/hitpay/webhook',
                'invoices/payment/hitpay/webhook',
                'invoices/payment/iyzipay/callback',
                'invoices/payment/aamarpay/success',
                'invoices/payment/aamarpay/callback',
                'invoices/payment/midtrans/success',
                'invoices/payment/midtrans/callback',
                'invoices/payment/easebuzz/success',
                'invoices/payment/easebuzz/failure',
                'api/media/batch',
            ],
        );

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            // Exceptions to let Laravel handle natively (redirects, specific responses, etc.)
            if (
                $e instanceof \Illuminate\Validation\ValidationException ||
                $e instanceof \Illuminate\Auth\AuthenticationException ||
                $e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                $e instanceof \Illuminate\Session\TokenMismatchException ||
                $e instanceof \Illuminate\Http\Exceptions\HttpResponseException
            ) {
                return null;
            }

            // First pass: convert specific Spatie exception to standard 403 HttpException
            // so we can handle it uniformly below.
            if ($e instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
                $e = new \Symfony\Component\HttpKernel\Exception\HttpException(
                    403,
                    'USER DOES NOT HAVE THE RIGHT PERMISSIONS.',
                    $e
                );
            }

            // Handle HTTP routing via Inertia
            if ($request->is('api/*') || $request->wantsJson()) {
                return null; // Let Laravel handle JSON API errors via Default
            }

            // If we are in debug mode, let Laravel show its nice Ignition error page for non-HTTP exceptions (500s)
            if (config('app.debug') && !($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface)) {
                return null;
            }

            $response = inertia()->render('errors/Error', [
                'status' => $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500,
                'message' => $e->getMessage() ?: 'Internal Server Error',
            ]);

            return $response->toResponse($request)
                ->setStatusCode($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500);
        });
    })->create();
