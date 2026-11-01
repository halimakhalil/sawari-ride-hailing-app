<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FareEstimateController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\RideController;
use Illuminate\Support\Facades\Route;

/*
| All routes are prefixed with /api. Full contract: public/openapi.yaml (served at /docs).
*/

Route::get('/health', [MetaController::class, 'health']);
Route::get('/locations', [MetaController::class, 'locations']);

Route::middleware('throttle:login')->prefix('auth')->group(function () {
    Route::post('/rider/login', [AuthController::class, 'riderLogin']);
    Route::post('/driver/login', [AuthController::class, 'driverLogin']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Riders and assigned / eligible drivers (authorised by RidePolicy).
    Route::get('/rides/{ride}', [RideController::class, 'show'])->whereNumber('ride');

    Route::middleware('actor:rider')->group(function () {
        Route::post('/fare-estimates', FareEstimateController::class);
        Route::get('/rides', [RideController::class, 'index']);
        Route::post('/rides', [RideController::class, 'store']);
        Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel'])->whereNumber('ride');
    });

    Route::middleware('actor:driver')->group(function () {
        Route::post('/rides/{ride}/accept', [RideController::class, 'accept'])->whereNumber('ride');
        Route::post('/rides/{ride}/start', [RideController::class, 'start'])->whereNumber('ride');
        Route::post('/rides/{ride}/complete', [RideController::class, 'complete'])->whereNumber('ride');

        Route::get('/driver/rides/available', [DriverController::class, 'availableRides']);
        Route::get('/driver/rides/current', [DriverController::class, 'currentRide']);
        Route::put('/driver/availability', [DriverController::class, 'updateAvailability']);
    });
});
