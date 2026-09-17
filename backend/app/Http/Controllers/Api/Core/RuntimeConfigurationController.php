<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;

/**
 * Publishes the safe subset of runtime settings required by unauthenticated clients.
 */
class RuntimeConfigurationController extends Controller
{
    public function __invoke(RuntimeConfiguration $configuration): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['configuration' => $configuration->publicValues()],
        ]);
    }
}
