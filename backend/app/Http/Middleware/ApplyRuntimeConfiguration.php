<?php

namespace App\Http\Middleware;

use App\Services\RuntimeConfiguration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies persisted runtime settings before a request reaches application code.
 */
class ApplyRuntimeConfiguration
{
    public function __construct(private readonly RuntimeConfiguration $configuration) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->configuration->apply();

        return $next($request);
    }
}
