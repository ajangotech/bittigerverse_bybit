<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'viewLogin'])->name('login.form');
Route::post('/', [AuthController::class, 'login'])->name('login');

//Route::get('/register', [AuthController::class, 'viewRegister'])->name('register.form');
//Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/ads', [AdsController::class, 'manageAds'])->name('manageads');
    Route::post('/dashboard/ads/store', [AdsController::class, 'store']);
    Route::post('/dashboard/ads/update-price', [AdsController::class, 'updatePrice']);
    Route::delete('/dashboard/ads/{id}', [AdsController::class, 'destroy']);

    Route::get('/dashboard/users', [UserController::class, 'index'])->name('dashboard.users');
    Route::post('/dashboard/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::post('/dashboard/users', [UserController::class, 'store']);
    Route::delete('/dashboard/users/{id}', [UserController::class, 'destroy']);

    Route::get('/dashboard/payments', [PaymentController::class, 'index'])->name('dashboard.payments');

    Route::get('/dashboard/orders', [OrdersController::class, 'orders'])->name('dashboard.orders');

    Route::post('/dashboard/change-password', [DashboardController::class, 'changePassword'])->middleware('auth');

    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
});