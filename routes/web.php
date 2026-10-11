<?php

use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Site\LocaleController;
use App\Http\Controllers\Site\ManifestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'site.home')->name('home');

Route::get('/language/{locale}', LocaleController::class)->name('locale.switch');
Route::get('/site.webmanifest', ManifestController::class)->name('manifest');

// Reference page for the public design system (local development only).
if (app()->isLocal()) {
    Route::view('/styleguide', 'site.styleguide')->name('styleguide');
}

// Admin portal (Vue SPA). Every /admin URL returns the shell; the client router resolves it.
Route::get('/admin/{any?}', SpaController::class)->where('any', '.*')->name('admin');
