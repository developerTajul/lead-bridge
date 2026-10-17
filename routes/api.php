<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadConversionController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Leads Endpoints
    Route::post('/leads/capture', LeadCaptureController::class)
        ->middleware(['throttle:60,1']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::apiResource('activities', ActivityController::class);
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('contacts', ContactController::class);
        Route::apiResource('pipelines', PipelineController::class);
        Route::apiResource('deals', DealController::class);
        // Update and delete have no use case on the lead service yet, so the
        // resource exposes only the three actions that are implemented.
        Route::apiResource('leads', LeadController::class)->only(['index', 'show', 'store']);
        Route::post('leads/{lead}/convert', [LeadConversionController::class, 'convert']);
        Route::get('pipelines/{pipeline}/kanban', [PipelineController::class, 'kanban']);
        Route::get('dashboard/metrics', [DashboardController::class, 'metrics']);
    });

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