<?php

use App\Http\Controllers\Web\Backend\ActivityController;
use App\Http\Controllers\Web\Backend\AuthController;
use App\Http\Controllers\Web\Backend\CompanyController;
use App\Http\Controllers\Web\Backend\ContactController;
use App\Http\Controllers\Web\Backend\DashboardController;
use App\Http\Controllers\Web\Backend\DealController;
use App\Http\Controllers\Web\Backend\LeadController;
use App\Http\Controllers\Web\Backend\PipelineController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


// Auth routes (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'create'])->name('register');
    Route::post('/register', [AuthController::class, 'store'])->name('register.store');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AuthController::class, 'me'])->name('profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('leads', LeadController::class);
        Route::resource('activities', ActivityController::class);
        Route::resource('companies', CompanyController::class);
        Route::resource('contacts', ContactController::class);
        Route::resource('deals', DealController::class);
        Route::resource('pipelines', PipelineController::class);

        // The board is not one of the resource actions, so it is declared
        // after the resource and named to sit alongside it.
        Route::get('pipelines/{pipeline}/kanban', [PipelineController::class, 'kanban'])
            ->name('pipelines.kanban');
    });
});
