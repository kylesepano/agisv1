<?php

use App\Http\Controllers\Api\Shared\HealthController;
use Illuminate\Support\Facades\Route;

// The React build is served by Apache for the root and unknown browser routes.
// Keep this lightweight Laravel health endpoint outside the SPA fallback.
Route::get('/health', HealthController::class);

// The backend is API-first; this route remains available when the application
// is run directly with `php artisan serve` outside the Render image.
Route::get('/', function () {
    return view('welcome');
});
