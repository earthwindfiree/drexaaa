<?php

use App\Http\Controllers\Api\Admin\AdminAccountsController;
use App\Http\Controllers\Api\Admin\AdminAuditLogsController;
use App\Http\Controllers\Api\Admin\AdminDepositsController;
use App\Http\Controllers\Api\Admin\AdminMarketsController;
use App\Http\Controllers\Api\Admin\AdminStrategiesController;
use App\Http\Controllers\Api\Admin\AdminTiersController;
use App\Http\Controllers\Api\Admin\AdminTransactionsController;
use App\Http\Controllers\Api\Admin\AdminUsersController;
use App\Http\Controllers\Api\Admin\AdminWithdrawalsController;
use App\Http\Controllers\Api\Admin\DashboardSummaryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserAccountController;
use App\Http\Controllers\Api\UserDepositsController;
use App\Http\Controllers\Api\UserMarketsController;
use App\Http\Controllers\Api\UserNotificationsController;
use App\Http\Controllers\Api\UserTransactionsController;
use App\Http\Controllers\Api\UserWalletController;
use App\Http\Controllers\Api\UserWithdrawalsController;
use App\Http\Middleware\EnsureVerifiedUser;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API running',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::get('/countries', [AuthController::class, 'countries']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/verification-notification', [AuthController::class, 'sendVerification'])
        ->middleware(['auth:sanctum', 'throttle:6,1']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum', EnsureVerifiedUser::class])->group(function () {
    Route::get('/profile', [AuthController::class, 'me']);
    Route::patch('/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:6,1');
    Route::patch('/profile/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::get('/dashboard', [UserAccountController::class, 'dashboard']);
    Route::get('/portfolio', [UserAccountController::class, 'portfolio']);
    Route::get('/markets', [UserMarketsController::class, 'index']);
    Route::get('/wallet', [UserWalletController::class, 'show']);
    Route::get('/deposits', [UserDepositsController::class, 'index']);
    Route::post('/deposits', [UserDepositsController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/deposits/{deposit}', [UserDepositsController::class, 'show']);
    Route::get('/withdrawals', [UserWithdrawalsController::class, 'index']);
    Route::post('/withdrawals', [UserWithdrawalsController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/withdrawals/{withdrawal}', [UserWithdrawalsController::class, 'show']);
    Route::get('/transactions', [UserTransactionsController::class, 'index']);
    Route::get('/notifications', [UserNotificationsController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [UserNotificationsController::class, 'markRead']);
    Route::patch('/notifications/read-all', [UserNotificationsController::class, 'markAllRead']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::get('/dashboard/summary', DashboardSummaryController::class);
    Route::get('/accounts', [AdminAccountsController::class, 'index']);
    Route::get('/accounts/{account}', [AdminAccountsController::class, 'show']);
    Route::patch('/accounts/{account}/simulation', [AdminAccountsController::class, 'updateSimulation']);
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::patch('/users/{user}/access', [AdminUsersController::class, 'updateAccess']);
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
    Route::patch('/strategies/{strategy}', [AdminStrategiesController::class, 'update']);
});
