<?php

use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Site\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/language/{locale}', LocaleController::class)->name('locale.switch');

// Admin portal (Vue SPA). Every /admin URL returns the shell; the client router resolves it.
Route::get('/admin/{any?}', SpaController::class)->where('any', '.*')->name('admin');
