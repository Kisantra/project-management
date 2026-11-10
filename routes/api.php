<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ClientIntakeController;
use Illuminate\Support\Facades\Route;

// Version 1 API Routes
Route::prefix('v1')->middleware('api')->group(function () {
    // User routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'show']);
        Route::get('/{user}', [UserController::class, 'detail']);
    });

    // Project routes 
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::get('/{project}', [ProjectController::class, 'show']);
    });

    // Client routes
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::get('/{client}', [ClientController::class, 'show']); 
    });

    // Activity routes
    Route::prefix('activities')->group(function () {
        Route::get('/', [ActivityController::class, 'index']);
        Route::get('/{activity}', [ActivityController::class, 'show']);
    });

    // Serah terima klien dari Client Management (token bersama, bukan sesi)
    Route::prefix('intake')->middleware('client-management.token')->group(function () {
        Route::post('/clients', [ClientIntakeController::class, 'store']);
        Route::post('/clients/{cmLeadId}/withdraw', [ClientIntakeController::class, 'withdraw'])
            ->whereNumber('cmLeadId');
    });
});