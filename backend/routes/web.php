<?php

use App\Http\Controllers\Api\Shared\HealthController;
use Illuminate\Support\Facades\Route;

// Render exposes this lightweight readiness endpoint for the API service.
Route::get('/health', HealthController::class);

// The React application is deployed separately from frontend/ on Vercel.
// This response makes accidental visits to the API origin self-explanatory.
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'service' => 'api',
        'status' => 'ok',
    ]);
});
