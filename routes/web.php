<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BybitController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;


Route::get('/', [AuthController::class, 'viewLogin'])->name('login.form');
Route::post('/', [AuthController::class, 'login'])->name('login');

Route::get('/register', [AuthController::class, 'viewRegister'])->name('register.form');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::middleware('auth')->group(function () {

    Route::post('/bybit/p2p-market', function (Request $request) {
        $payload = [
            'userId'             => '',
            'tokenId'            => $request->input('tokenId', 'BTC'),
            'currencyId'         => $request->input('currencyId', 'NGN'),
            'payment'            => [],
            'side'               => (string) $request->input('side', '0'),
            'size'               => (string) $request->input('size', '10'),
            'page'               => (string) $request->input('page', '1'),
            'amount'             => '',
            'vaMaker'            => true,
            'authMaker'          => false,
            'bulkMaker'          => true,
            'canTrade'           => true,
            'verificationFilter' => 0,
            'sortType'           => 'OVERALL_RANKING',
            'sortStrategyCode'   => 'DEFAULT_BUY',
            'paymentPeriod'      => [],
            'itemRegion'         => 1,
            'countryCode'        => '',
            'tradeWith'          => false,
        ];

        $response = Http::withHeaders([
            'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://www.bybitglobal.com/x-api/fiat/otc/item/online', $payload);

        return response()->json($response->json());
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/ads', [AdsController::class, 'manageAds'])->name('manageads');
    Route::get('/dashboard/adslist', [AdsController::class, 'manageAdsList'])->name('manageadslist');
    Route::post('/dashboard/ads/store', [AdsController::class, 'store']);
    Route::post('/dashboard/ads/update-price', [AdsController::class, 'updatePrice']);
    Route::delete('/dashboard/ads/{id}', [AdsController::class, 'destroy']);

    Route::get('/dashboard/users', [UserController::class, 'index'])->name('dashboard.users');
    Route::post('/dashboard/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::post('/dashboard/users', [UserController::class, 'store']);
    Route::delete('/dashboard/users/{id}', [UserController::class, 'destroy']);

    Route::get('/dashboard/payments', [PaymentController::class, 'index'])->name('dashboard.payments');

    Route::get('/dashboard/orders', [OrdersController::class, 'orders'])->name('dashboard.orders');

    Route::post('/competitors/store', [CompetitorController::class, 'store'])->name('dashboard.com.store');

    Route::post('/competitors/update-price', [CompetitorController::class, 'updatePrice'])->name('competitors.updatePrice');

    Route::get('/dashboard/competitor', [CompetitorController::class, 'competitor'])->name('dashboard.competitor');

    Route::get('/dashboard/competitorpro', [CompetitorController::class, 'competitorpro'])->name('dashboard.competitorpro');

    Route::get('/dashboard/switchmerchant', [CompetitorController::class, 'switchmerchant'])->name('dashboard.switchmerchant');

    Route::post('/dashboard/change-password', [DashboardController::class, 'changePassword'])->middleware('auth');

    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
});