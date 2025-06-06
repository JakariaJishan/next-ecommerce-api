<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContestController;
use App\Http\Controllers\ContestEntryController;
use App\Http\Controllers\ContestEntryVoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguagePreferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchHistoryController;
use App\Http\Controllers\SellerInfoController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\UserPreference;
use App\Http\Controllers\UserReviewController;
use App\Http\Controllers\WishListController;
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

//products routes
Route::resource('products', ProductController::class)->except(['create', 'edit']);

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

//carts routes
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    // Add product to cart
    Route::post('/add-to-cart/{product_id}', [CartController::class, 'addToCart']);

    // Remove product from cart
    Route::delete('/remove-from-cart/{cart_id}/{product_id}', [CartController::class, 'removeFromCart']);

    Route::patch('/{cart_id}/increase/{cart_item_id}', [CartController::class, 'increaseQuantity']);
    Route::patch('/{cart_id}/decrease/{cart_item_id}', [CartController::class, 'decreaseQuantity']);
});

//addresses routes
Route::post('/checkout/addresses', [AddressController::class, 'store']);

Route::get('/checkout/addresses', [AddressController::class, 'index']);

Route::patch('/checkout/addresses/{id}', [AddressController::class, 'update']);

Route::delete('/checkout/addresses/{id}', [AddressController::class, 'destroy']);

// Orders routes
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/order/{order_id}', [OrderController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store']);
//Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

// Payments
//Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);

//wishlist routes
Route::post('/wishlist', [WishlistController::class, 'store']);
Route::get('/wishlists', [WishlistController::class, 'index']);
Route::delete('/wishlist/{product_id}', [WishlistController::class, 'destroy']);

//payment methods routes
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::post('/payment-method', [PaymentMethodController::class, 'store']);
Route::patch('/payment-method/{payment_method_id}', [PaymentMethodController::class, 'update']);
Route::delete('/payment-method/{payment_method_id}', [PaymentMethodController::class, 'destroy']);

//user(my account) account routes
Route::get('/account-overview', [UserAccountController::class, 'accountOverView']);
Route::patch('/account-overview-update-user-info', [UserAccountController::class, 'updateUserInfo']);
Route::patch('/account-overview-update-user-notification', [UserAccountController::class, 'updateUserNotification']);

//user preference routes
Route::post('/language-preference', [UserPreference::class, 'storeAndUpdateLanguagePreference']);
Route::post('/currency-preference', [UserPreference::class, 'storeAndUpdateCurrencyPreference']);
Route::post('/timezone-preference', [UserPreference::class, 'storeAndUpdateTimezonePreference']);

// Product Reviews routes
Route::prefix('reviews')->group(function () {
    Route::get('/products', [ProductReviewController::class, 'index']);
    Route::post('/product', [ProductReviewController::class, 'store']);
    Route::get('/product/{id}', [ProductReviewController::class, 'show']);
    Route::put('/product/{id}', [ProductReviewController::class, 'update']);
    Route::delete('/product/{id}', [ProductReviewController::class, 'destroy']);
    Route::get('/products/summary/{productId}', [ProductReviewController::class, 'productRatingSummary']);
});

// User Reviews routes
Route::prefix('reviews')->group(function () {
    Route::get('/users', [UserReviewController::class, 'index']);
    Route::post('/users', [UserReviewController::class, 'store']);
    Route::get('/users/{id}', [UserReviewController::class, 'show']);
    Route::put('/users/{id}', [UserReviewController::class, 'update']);
    Route::delete('/users/{id}', [UserReviewController::class, 'destroy']);
    Route::get('/users/summary/{userId}', [UserReviewController::class, 'userRatingSummary']);
});
