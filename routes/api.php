<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BootstrapController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin portal API
|--------------------------------------------------------------------------
|
| Consumed by the admin SPA with Sanctum's cookie-based session auth.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {

    Route::controller(AuthController::class)->prefix('auth')->group(function () {
        Route::post('login', 'login')->middleware('throttle:admin-login')->name('login');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

        Route::get('bootstrap', BootstrapController::class)->name('bootstrap');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Users
        Route::get('users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
        Route::get('users/role-options', [UserController::class, 'roleOptions'])->middleware('can:users.view')->name('users.role-options');
        Route::post('users', [UserController::class, 'store'])->middleware('can:users.create')->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.view')->name('users.show');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.update')->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');

        // Roles
        Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('can:roles.view')->name('roles.permissions');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:roles.create')->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.update')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('roles.destroy');

        // Media library
        Route::get('media/usages', [MediaController::class, 'usages'])->middleware('can:media.view')->name('media.usages');
        Route::get('media', [MediaController::class, 'index'])->middleware('can:media.view')->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->middleware('can:media.create')->name('media.store');
        Route::get('media/{media}', [MediaController::class, 'show'])->middleware('can:media.view')->name('media.show');
        Route::put('media/{media}', [MediaController::class, 'update'])->middleware('can:media.update')->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->middleware('can:media.delete')->name('media.destroy');

        // Menus
        Route::get('menus', [MenuController::class, 'index'])->middleware('can:menus.view')->name('menus.index');
        Route::get('menus/{location}', [MenuController::class, 'show'])->middleware('can:menus.view')->name('menus.show');
        Route::put('menus/{location}', [MenuController::class, 'update'])->middleware('can:menus.update')->name('menus.update');

        // Pages & campaigns (permissions are checked per page type in the controller)
        Route::get('page-builder', [PageController::class, 'builder'])->name('page-builder');
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show');
        Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
        Route::get('pages/{page}/preview-url', [PageController::class, 'previewUrl'])->name('pages.preview-url');

        // Travel: services & packages
        Route::get('services', [ServiceController::class, 'index'])->middleware('can:services.view')->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->middleware('can:services.create')->name('services.store');
        Route::put('services/reorder', [ServiceController::class, 'reorder'])->middleware('can:services.update')->name('services.reorder');
        Route::put('services/{service}', [ServiceController::class, 'update'])->middleware('can:services.update')->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->middleware('can:services.delete')->name('services.destroy');
        Route::get('packages/schema', [PackageController::class, 'schema'])->middleware('can:packages.view')->name('packages.schema');
        Route::get('packages', [PackageController::class, 'index'])->middleware('can:packages.view')->name('packages.index');
        Route::post('packages', [PackageController::class, 'store'])->middleware('can:packages.create')->name('packages.store');
        Route::get('packages/{package}', [PackageController::class, 'show'])->middleware('can:packages.view')->name('packages.show');
        Route::put('packages/{package}', [PackageController::class, 'update'])->middleware('can:packages.update')->name('packages.update');
        Route::post('packages/{package}/duplicate', [PackageController::class, 'duplicate'])->middleware('can:packages.create')->name('packages.duplicate');
        Route::delete('packages/{package}', [PackageController::class, 'destroy'])->middleware('can:packages.delete')->name('packages.destroy');

        // Blog & news
        Route::get('posts/meta', [PostController::class, 'meta'])->middleware('can:blog.view')->name('posts.meta');
        Route::get('posts', [PostController::class, 'index'])->middleware('can:blog.view')->name('posts.index');
        Route::post('posts', [PostController::class, 'store'])->middleware('can:blog.create')->name('posts.store');
        Route::get('posts/{post}', [PostController::class, 'show'])->middleware('can:blog.view')->name('posts.show');
        Route::put('posts/{post}', [PostController::class, 'update'])->middleware('can:blog.update')->name('posts.update');
        Route::get('posts/{post}/preview-url', [PostController::class, 'previewUrl'])->middleware('can:blog.view')->name('posts.preview-url');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->middleware('can:blog.delete')->name('posts.destroy');
        Route::post('post-categories', [PostCategoryController::class, 'store'])->middleware('can:blog.update')->name('post-categories.store');
        Route::put('post-categories/reorder', [PostCategoryController::class, 'reorder'])->middleware('can:blog.update')->name('post-categories.reorder');
        Route::put('post-categories/{category}', [PostCategoryController::class, 'update'])->middleware('can:blog.update')->name('post-categories.update');
        Route::delete('post-categories/{category}', [PostCategoryController::class, 'destroy'])->middleware('can:blog.update')->name('post-categories.destroy');

        // Reviews
        Route::get('reviews', [ReviewController::class, 'index'])->middleware('can:reviews.view')->name('reviews.index');
        Route::post('reviews', [ReviewController::class, 'store'])->middleware('can:reviews.create')->name('reviews.store');
        Route::put('reviews/reorder', [ReviewController::class, 'reorder'])->middleware('can:reviews.update')->name('reviews.reorder');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->middleware('can:reviews.update')->name('reviews.update');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->middleware('can:reviews.delete')->name('reviews.destroy');

        // Gallery
        Route::get('gallery', [GalleryController::class, 'index'])->middleware('can:gallery.view')->name('gallery.index');
        Route::post('gallery', [GalleryController::class, 'store'])->middleware('can:gallery.create')->name('gallery.store');
        Route::get('gallery/{album}', [GalleryController::class, 'show'])->middleware('can:gallery.view')->name('gallery.show');
        Route::put('gallery/{album}', [GalleryController::class, 'update'])->middleware('can:gallery.update')->name('gallery.update');
        Route::delete('gallery/{album}', [GalleryController::class, 'destroy'])->middleware('can:gallery.delete')->name('gallery.destroy');
        Route::post('gallery/{album}/items', [GalleryController::class, 'addItems'])->middleware('can:gallery.update')->name('gallery.items.store');
        Route::put('gallery/{album}/items/{item}', [GalleryController::class, 'updateItem'])->middleware('can:gallery.update')->name('gallery.items.update');
        Route::post('gallery/{album}/items/remove', [GalleryController::class, 'removeItems'])->middleware('can:gallery.update')->name('gallery.items.destroy');

        // Bookings & payments
        Route::get('bookings', [BookingController::class, 'index'])->middleware('can:bookings.view')->name('bookings.index');
        Route::get('bookings/export', [BookingController::class, 'export'])->middleware('can:bookings.view')->name('bookings.export');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->middleware('can:bookings.view')->name('bookings.show');
        Route::put('bookings/{booking}', [BookingController::class, 'update'])->middleware('can:bookings.update')->name('bookings.update');
        Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->middleware('can:bookings.delete')->name('bookings.destroy');
        Route::get('bookings/{booking}/documents/{document}', [BookingController::class, 'document'])->middleware('can:bookings.view')->name('bookings.document');
        Route::post('bookings/{booking}/payments', [BookingController::class, 'storePayment'])->middleware('can:payments.create')->name('bookings.payments.store');
        Route::delete('bookings/{booking}/payments/{payment}', [BookingController::class, 'destroyPayment'])->middleware('can:payments.create')->name('bookings.payments.destroy');

        // Inquiries
        Route::get('trash', [TrashController::class, 'index'])->middleware('can:trash.view')->name('trash.index');
        Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->whereNumber('id')->middleware('can:trash.restore')->name('trash.restore');

        Route::get('inquiries', [InquiryController::class, 'index'])->middleware('can:inquiries.view')->name('inquiries.index');
        Route::get('inquiries/export', [InquiryController::class, 'export'])->middleware('can:inquiries.view')->name('inquiries.export');
        Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])->middleware('can:inquiries.view')->name('inquiries.show');
        Route::put('inquiries/{inquiry}', [InquiryController::class, 'update'])->middleware('can:inquiries.update')->name('inquiries.update');
        Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])->middleware('can:inquiries.delete')->name('inquiries.destroy');

        // Settings
        Route::get('settings', [SettingsController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
        Route::get('settings/{group}', [SettingsController::class, 'show'])->middleware('can:settings.view')->name('settings.show');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->middleware('can:settings.update')->name('settings.update');
    });
});
