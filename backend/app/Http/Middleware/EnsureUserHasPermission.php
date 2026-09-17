<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects protected requests unless at least one active role grants the permission.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response|JsonResponse
    {
        // A pipe-delimited contract supports compatibility aliases while
        // keeping one route registration. Existing single permissions remain
        // unchanged.
        $permissions = collect(preg_split('/[|,]/', $permission) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->values()
            ->all();

        if (! $request->user()?->hasAnyPermission($permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
