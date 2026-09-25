<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductLikeController;
use Illuminate\Support\Facades\Route;

// ── Health ────────────────────────────────────────────────────────────────────

Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'message' => 'NomadsHunt API is running.',
]));

// ── Auth (public) ─────────────────────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Public catalog ────────────────────────────────────────────────────────────

Route::get('/products/filter-options', [ProductController::class, 'filterOptions']);
Route::get('/products',                [ProductController::class, 'index']);
Route::get('/products/{product}',      [ProductController::class, 'show']);

// Public claim history for a product (used on product detail page)
Route::get('/products/{product}/claims', [ClaimController::class, 'productClaims']);

// Public homepage announcement — only the deterministic active item is exposed.
Route::get('/announcements/active', [AnnouncementController::class, 'active']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);

// ── Authenticated routes ───────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Claim actions (customers only)
    Route::middleware('customer')->group(function () {
        Route::post('/products/{product}/mine',  [ClaimController::class, 'mine']);
        Route::post('/products/{product}/steal', [ClaimController::class, 'steal']);
        Route::post('/products/{product}/grab',  [ClaimController::class, 'grab']);

        Route::get('/my-claims',            [ClaimController::class, 'myClaims']);
        Route::get('/my-orders',            [OrderController::class, 'myOrders']);

        Route::get('/likes',                         [ProductLikeController::class, 'index']);
        Route::post('/products/{product}/like',      [ProductLikeController::class, 'store']);
        Route::delete('/products/{product}/like',   [ProductLikeController::class, 'destroy']);
        Route::get('/orders/{order}',       [OrderController::class, 'show']);
        Route::post('/orders/{order}/pay',  [OrderController::class, 'pay']);
    });

    // ── Admin routes ──────────────────────────────────────────────────────────

    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [Admin\DashboardController::class, 'index']);

        // Announcements
        Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
        Route::get('/announcements/{announcement}', [AdminAnnouncementController::class, 'show']);
        Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);
        Route::post('/announcements/{announcement}/upload-image', [AdminAnnouncementController::class, 'uploadImage']);
        Route::post('/announcements/{announcement}/publish', [AdminAnnouncementController::class, 'publish']);
        Route::post('/announcements/{announcement}/unpublish', [AdminAnnouncementController::class, 'unpublish']);
        Route::post('/announcements/{announcement}/archive', [AdminAnnouncementController::class, 'archive']);

        // Products
        Route::get('/products',                           [Admin\ProductController::class, 'index']);
        Route::post('/products',                          [Admin\ProductController::class, 'store']);
        Route::get('/products/{product}',                 [Admin\ProductController::class, 'show']);
        Route::put('/products/{product}',                 [Admin\ProductController::class, 'update']);
        Route::post('/products/{product}/upload-image',   [Admin\ProductController::class, 'uploadImage']);

        // Claims
        Route::get('/claims',                                [Admin\ClaimController::class, 'index']);
        Route::get('/products/{product}/claims',             [Admin\ClaimController::class, 'productClaims']);
        Route::post('/claims/{claim}/force-expire',          [Admin\ClaimController::class, 'forceExpire']);

        // Orders
        Route::get('/orders',           [Admin\OrderController::class, 'index']);
        Route::get('/orders/{order}',   [Admin\OrderController::class, 'show']);

        // Customers
        Route::get('/customers',        [Admin\CustomerController::class, 'index']);
        Route::get('/customers/{user}', [Admin\CustomerController::class, 'show']);

        // Activity logs
        Route::get('/activity-logs', [Admin\ActivityLogController::class, 'index']);
    });
});
