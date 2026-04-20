<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'viewLogin'])->name('login.form');
Route::post('/', [AuthController::class, 'login'])->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/ads', [AdsController::class, 'manageAds'])->name('manageads');
    Route::post('/dashboard/ads/update-price', [AdsController::class, 'updatePrice']);
    Route::delete('/dashboard/ads/{id}', [AdsController::class, 'destroy']);

    Route::get('/dashboard/users', [UserController::class, 'index'])->name('dashboard.users');
    Route::post('/dashboard/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::delete('/dashboard/users/{id}', [UserController::class, 'destroy']);

    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
});