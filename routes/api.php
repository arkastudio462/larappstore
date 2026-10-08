<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Api\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\DeveloperPortalController;
use App\Http\Controllers\Api\DeveloperProductController;
use App\Http\Controllers\Api\DeveloperVersionController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('api.auth.register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('me', [AuthController::class, 'show'])->name('api.auth.me');
        Route::put('me', [AuthController::class, 'update'])->name('api.auth.update');
    });
});

Route::post('developer/upgrade', [DeveloperController::class, 'upgrade'])
    ->middleware('auth')
    ->name('api.developer.upgrade');

Route::prefix('developer')
    ->middleware(['auth', 'role:developer,admin'])
    ->group(function (): void {
        Route::get('overview', [DeveloperPortalController::class, 'overview'])->name('api.developer.overview');
        Route::get('orders', [DeveloperPortalController::class, 'orders'])->name('api.developer.orders');

        Route::get('products', [DeveloperProductController::class, 'index'])->name('api.developer.products.index');
        Route::post('products', [DeveloperProductController::class, 'store'])->name('api.developer.products.store');
        Route::put('products/{product}', [DeveloperProductController::class, 'update'])->name('api.developer.products.update');
        Route::post('products/{product}/publish', [DeveloperProductController::class, 'publish'])->name('api.developer.products.publish');
        Route::post('products/{product}/archive', [DeveloperProductController::class, 'archive'])->name('api.developer.products.archive');
        Route::get('products/{product}/stats', [DeveloperProductController::class, 'stats'])->name('api.developer.products.stats');

        Route::post('products/{product}/versions', [DeveloperVersionController::class, 'store'])->name('api.developer.versions.store');
        Route::post('products/{product}/versions/{version}/publish', [DeveloperVersionController::class, 'publish'])->name('api.developer.versions.publish');

        Route::post('uploads', [UploadController::class, 'store'])->name('api.developer.uploads.store');
    });

/*
 * Semua pengguna terautentikasi boleh membeli produk (`app_purchase`).
 * Pembelian kredit upload dibatasi developer di dalam controller.
 */
Route::post('orders', [OrderController::class, 'store'])
    ->middleware('auth')
    ->name('api.orders.store');

Route::post('orders/{orderNo}/check', [OrderController::class, 'check'])
    ->middleware('auth')
    ->name('api.orders.check');

/*
 * Webhook server-to-server. Dikecualikan dari CSRF di `bootstrap/app.php`
 * dan diamankan lewat verifikasi tanda tangan Midtrans (PRD §6.3).
 */
Route::post('payments/midtrans/notification', [PaymentController::class, 'notification'])
    ->name('api.payments.midtrans.notification');

Route::get('me/purchases', [PurchaseController::class, 'index'])
    ->middleware('auth')
    ->name('api.me.purchases');

Route::post('reports', [ReportController::class, 'store'])
    ->middleware('auth')
    ->name('api.reports.store');

/*
 * Panel admin: hapus konten, laporan, kategori/koleksi, pengguna, pesanan,
 * dan statistik (PRD §7).
 */
Route::prefix('admin')
    ->middleware(['auth', 'role:admin'])
    ->group(function (): void {
        Route::delete('posts/{post}', [AdminContentController::class, 'destroyPost'])->name('api.admin.posts.destroy');
        Route::delete('comments/{comment}', [AdminContentController::class, 'destroyComment'])->name('api.admin.comments.destroy');
        Route::delete('products/{product}', [AdminContentController::class, 'destroyProduct'])->name('api.admin.products.destroy');
        Route::delete('reviews/{review}', [AdminContentController::class, 'destroyReview'])->name('api.admin.reviews.destroy');

        Route::get('reports', [AdminReportController::class, 'index'])->name('api.admin.reports.index');
        Route::post('reports/{report}/resolve', [AdminReportController::class, 'resolve'])->name('api.admin.reports.resolve');

        Route::get('categories', [AdminCategoryController::class, 'index'])->name('api.admin.categories.index');
        Route::post('categories', [AdminCategoryController::class, 'store'])->name('api.admin.categories.store');
        Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->name('api.admin.categories.update');
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('api.admin.categories.destroy');

        Route::get('collections', [AdminCollectionController::class, 'index'])->name('api.admin.collections.index');
        Route::post('collections', [AdminCollectionController::class, 'store'])->name('api.admin.collections.store');
        Route::put('collections/{collection}', [AdminCollectionController::class, 'update'])->name('api.admin.collections.update');
        Route::delete('collections/{collection}', [AdminCollectionController::class, 'destroy'])->name('api.admin.collections.destroy');

        Route::get('users', [AdminUserController::class, 'index'])->name('api.admin.users.index');
        Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('api.admin.users.update');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('api.admin.orders.index');
        Route::get('stats', [AdminStatsController::class, 'index'])->name('api.admin.stats');
    });

Route::get('feed', [FeedController::class, 'index'])->name('api.feed.index');

Route::get('categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('collections/{slug}', [CollectionController::class, 'show'])->name('api.collections.show');
Route::get('search', [SearchController::class, 'index'])->name('api.search.index');

Route::get('products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('api.products.show');
Route::get('products/{product}/versions', [ProductController::class, 'versions'])->name('api.products.versions');
Route::get('products/{product}/reviews', [ProductController::class, 'reviews'])->name('api.products.reviews');

Route::post('products/{product}/download', [DownloadController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('api.products.download');

Route::get('posts/{post}', [PostController::class, 'show'])->name('api.posts.show');
Route::get('posts/{post}/comments', [CommentController::class, 'index'])->name('api.posts.comments.index');

Route::middleware('auth')->group(function (): void {
    Route::post('posts', [PostController::class, 'store'])->name('api.posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('api.posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('api.posts.destroy');
    Route::post('posts/{post}/publish', [PostController::class, 'publish'])->name('api.posts.publish');

    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('api.posts.comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');

    Route::post('likes', [LikeController::class, 'toggle'])->name('api.likes.toggle');

    Route::get('notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');

    Route::post('uploads', [UploadController::class, 'store'])->name('api.uploads.store');

    Route::post('products/{product}/reviews', [ReviewController::class, 'store'])->name('api.products.reviews.store');
    Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('api.reviews.update');
});

Route::prefix('users')->group(function (): void {
    Route::get('{username}', [UserController::class, 'show'])->name('api.users.show');
    Route::get('{username}/posts', [UserController::class, 'posts'])->name('api.users.posts');
    Route::get('{username}/products', [UserController::class, 'products'])->name('api.users.products');
    Route::get('{username}/followers', [UserController::class, 'followers'])->name('api.users.followers');
    Route::get('{username}/following', [UserController::class, 'following'])->name('api.users.following');

    Route::post('{username}/follow', [UserController::class, 'follow'])
        ->middleware('auth')
        ->name('api.users.follow');
});
