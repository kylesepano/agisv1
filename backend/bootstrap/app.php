<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\ApplyRuntimeConfiguration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(ApplyRuntimeConfiguration::class);
        $middleware->statefulApi();
        // Render terminates TLS at its proxy and forwards the original scheme.
        // Trust the proxy headers so secure cookies and generated URLs remain
        // HTTPS without redirect loops.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The submitted data is invalid.',
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Your session has expired. Please sign in again.',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            report($exception);

            $details = strtolower($exception->getMessage());
            $isPlanningPackage = str_contains($request->path(), 'planning-package')
                || str_contains($details, 'aems_risk_matrix');
            $reference = 'DB-'.strtoupper(Str::random(8));
            $message = match (true) {
                $isPlanningPackage && (
                    str_contains($details, 'invalid input syntax for type numeric')
                    || str_contains($details, 'incorrect decimal value')
                    || str_contains($details, 'numeric value out of range')
                ) => 'The planning package contains a risk-matrix likelihood, impact, or score that is not a valid number. Enter numeric values only, such as 1, 2, or 2.5.',
                $isPlanningPackage && str_contains($details, 'all_audit_focuses') => 'The planning package cannot be saved because the server database is missing the latest risk-matrix focus field. Run the pending database migrations, then retry.',
                $isPlanningPackage && (
                    str_contains($details, 'duplicate key')
                    || str_contains($details, 'unique constraint')
                ) => 'The planning package contains a duplicate code or relationship. Check risk codes, objective links, procedure links, and working-paper references.',
                $isPlanningPackage && (
                    str_contains($details, 'foreign key')
                    || str_contains($details, 'violates foreign key constraint')
                ) => 'The planning package contains a link to a record that no longer exists or is outside this engagement. Refresh the workspace and reselect the affected relationship.',
                default => 'The server rejected the request because of a database constraint. Review the entered values and retry.',
            };

            return response()->json([
                'success' => false,
                'message' => $message.' Reference: '.$reference.'.',
                'errors' => ['save' => [$message]],
                'errorReference' => $reference,
            ], 500, [
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });
    })->create();
