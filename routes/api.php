<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BootstrapController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
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
        Route::post('forgot-password', 'forgotPassword')->middleware('throttle:5,1')->name('password.email');
        Route::post('reset-password', 'resetPassword')->middleware('throttle:5,1')->name('password.update');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

        Route::get('bootstrap', BootstrapController::class)->name('bootstrap');

        // Users
        Route::get('users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
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

        // Settings
        Route::get('settings/{group}', [SettingsController::class, 'show'])->middleware('can:settings.view')->name('settings.show');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->middleware('can:settings.update')->name('settings.update');
    });
});
