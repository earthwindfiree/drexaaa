<?php

use App\Http\Controllers\Api\Admin\AdminAccountsController;
use App\Http\Controllers\Api\Admin\AdminAuditLogsController;
use App\Http\Controllers\Api\Admin\AdminDepositsController;
use App\Http\Controllers\Api\Admin\AdminMarketsController;
use App\Http\Controllers\Api\Admin\AdminTiersController;
use App\Http\Controllers\Api\Admin\AdminTransactionsController;
use App\Http\Controllers\Api\Admin\AdminUsersController;
use App\Http\Controllers\Api\Admin\AdminWithdrawalsController;
use App\Http\Controllers\Api\Admin\DashboardSummaryController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API running',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::get('/dashboard/summary', DashboardSummaryController::class);
    Route::get('/accounts', [AdminAccountsController::class, 'index']);
    Route::get('/accounts/{account}', [AdminAccountsController::class, 'show']);
    Route::patch('/accounts/{account}/simulation', [AdminAccountsController::class, 'updateSimulation']);
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::get('/markets', [AdminMarketsController::class, 'index']);
    Route::patch('/markets/{asset}/status', [AdminMarketsController::class, 'updateAssetStatus']);
    Route::patch('/markets/{asset}/price', [AdminMarketsController::class, 'updatePrice']);
    Route::get('/markets/{asset}/wallets', [AdminMarketsController::class, 'wallets']);
    Route::post('/markets/{asset}/wallets', [AdminMarketsController::class, 'storeWallet']);
    Route::patch('/wallets/{wallet}/status', [AdminMarketsController::class, 'updateWalletStatus']);
    Route::get('/deposits', [AdminDepositsController::class, 'index']);
    Route::get('/deposits/{deposit}', [AdminDepositsController::class, 'show']);
    Route::post('/deposits/{deposit}/confirm', [AdminDepositsController::class, 'confirm']);
    Route::post('/deposits/{deposit}/reject', [AdminDepositsController::class, 'reject']);
    Route::get('/withdrawals', [AdminWithdrawalsController::class, 'index']);
    Route::get('/withdrawals/{withdrawal}', [AdminWithdrawalsController::class, 'show']);
    Route::post('/withdrawals/{withdrawal}/approve', [AdminWithdrawalsController::class, 'approve']);
    Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalsController::class, 'reject']);
    Route::get('/transactions', [AdminTransactionsController::class, 'index']);
    Route::get('/audit-logs', [AdminAuditLogsController::class, 'index']);
    Route::get('/tiers', [AdminTiersController::class, 'index']);
    Route::patch('/tiers/{tier}', [AdminTiersController::class, 'update']);
});
