<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DashboardApiController;
use App\Http\Controllers\API\AdsApiController;
use App\Http\Controllers\API\UserApiController;
use App\Http\Controllers\API\BotApiController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {

    // DASHBOARD
    Route::get('/dashboard/stats', [DashboardApiController::class, 'stats']);
    Route::post('/dashboard/change-password', [DashboardApiController::class, 'changePassword']);

    // ADS
    Route::post('/dashboard/ads/list', [AdsApiController::class, 'list']);
    Route::post('/dashboard/ads/update-price', [AdsApiController::class, 'updatePrice']);

    // ORDERS & PAYMENTS
    Route::post('/dashboard/orders/list', [DashboardApiController::class, 'orders']);
    Route::post('/dashboard/payments/list', [DashboardApiController::class, 'payments']);

    // USERS (ADMIN)
    Route::get('/dashboard/users', [UserApiController::class, 'index']);
    Route::post('/dashboard/users', [UserApiController::class, 'store']);
    Route::delete('/dashboard/users/{id}', [UserApiController::class, 'destroy']);

    // BOT
    Route::post('/dashboard/bot/online', [BotApiController::class, 'online']);
});