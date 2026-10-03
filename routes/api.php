<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadCaptureController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Leads Endpoints
    Route::post('/leads/capture', LeadCaptureController::class)
        ->middleware(['throttle:60,1']);

    // Auth Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Protected Endpoints (auth:sanctum)
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

});