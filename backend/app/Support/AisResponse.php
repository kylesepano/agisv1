<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/** Applies the private-response controls retained by the AIS-4 hardening contract under AIS-5A. */
final class AisResponse
{
    public static function json(array $payload, int $status = 200, bool $cacheable = false): JsonResponse
    {
        return response()->json($payload, $status)->withHeaders([
            'Cache-Control' => $cacheable
                ? 'private, max-age=' . max(0, (int) config('ais.read_cache_seconds', 30)) . ', must-revalidate'
                : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
