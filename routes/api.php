<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContestController;
use App\Http\Controllers\ContestEntryController;
use App\Http\Controllers\ContestEntryVoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchHistoryController;
use App\Http\Controllers\SellerInfoController;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Your routes that require authentication
    Route::post('/broadcasting/auth', function () {
        return Broadcast::auth(request());
    });
});

// User Logout
Route::post('/logout', [AuthController::class, 'logout']);

// Current User Sessions
Route::get('/current-user-sessions', [AuthController::class, 'currentUserSessions']);

// Update User Info
Route::patch('/update-user-info', [AuthController::class, 'updateUserInfo']);

// Update Current User Password
Route::patch('/update-password', [AuthController::class, 'updateCurrentUserPassword']);

// Get Current User Info
Route::get('/current-user-info', [AuthController::class, 'currentUserInfo']);

//2FA Routes
Route::post('/enable-2fa', [AuthController::class, 'enableTwoFa']);
Route::post('/activate-2fa', [AuthController::class, 'activateTwoFa']);
Route::get('/show-recovery-codes', [AuthController::class, 'showRecoveryCodes']);
Route::get('/regenerate-recovery-code', [AuthController::class, 'regenerateRecoveryCodes']);
Route::post('/disable-twofa', [AuthController::class, 'disable2FA']);

// User Registration
Route::post('/register', [AuthController::class, 'register']);

// Email Verification
Route::get('/email/verify', [AuthController::class, 'verifyEmail']);
Route::post('/resend-email-verification', [AuthController::class, 'resendVerificationEmail']);

// User Login
Route::post('/login', [AuthController::class, 'login']);

// Login with 2FA
Route::post('/login-with-twofa', [AuthController::class, 'loginWithTwoFa']);

// Login with Recovery Code
Route::post('/login-with-recovery-code', [AuthController::class, 'loginWithRecoveryCode']);

// Google Sign-In
Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Password Reset
Route::post('/send-reset-password-instruction', [AuthController::class, 'sendResetPasswordInstruction']);
Route::patch('/reset-password', [AuthController::class, 'resetPassword']);

//categories routes
Route::resource('categories', CategoryController::class)->except(['create', 'edit']);

//ads routes
Route::resource('ads', AdsController::class)->except(['create', 'edit']);

//blog posts routes
Route::resource('blog-posts', BlogPostController::class)->except(['create', 'edit']);

//search route
Route::get('search', [SearchController::class, 'adsSearch']);

// search histories controller
Route::resource('search-histories', SearchHistoryController::class)
    ->only('index')->middleware('auth:sanctum');

//report routes
Route::resource('reports', ReportController::class)->only(['index', 'store', 'show', 'update']);

//contest routes
Route::resource('contests', ContestController::class)
    ->except(['create', 'edit']);

//contest entry route
Route::resource('contests-entries', ContestEntryController::class)
    ->only(['index', 'store']);

//contest entry vote route
Route::resource('contests-entries-votes', ContestEntryVoteController::class)
    ->only(['index']);

//seller info route
//Route::resource('seller-infos', SellerInfoController::class)
//    ->only(['index',]);
Route::get('sellers', [SellerInfoController::class, 'sellersInfo']);
Route::get('seller/{userId}', [SellerInfoController::class, 'individualSellerInfo']);

//role CRUD routes
Route::resource('roles', RoleController::class)->except(['create', 'edit']);

//permission CRUD routes
Route::resource('permissions', PermissionController::class)->except(['create', 'edit']);

//admin CRUD routes
Route::resource('admins', AdminController::class)->except(['create', 'edit']);;

//dashboard routes
Route::get('dashboards/summary', [DashboardController::class, 'dashboardSummary']);
Route::get('dashboards/top-sold-ads', [DashboardController::class, 'topSoldAds']);

//notifications routes
Route::resource('notifications', NotificationController::class)->except(['create', 'edit']);
Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
