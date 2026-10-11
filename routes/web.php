<?php

use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Site\FormController;
use App\Http\Controllers\Site\LocaleController;
use App\Http\Controllers\Site\ManifestController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Requests\Admin\PageSaveRequest;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', LocaleController::class)->name('locale.switch');
Route::get('/site.webmanifest', ManifestController::class)->name('manifest');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::post('/forms/{section}', [FormController::class, 'submit'])
    ->middleware('throttle:6,1')
    ->name('forms.submit');

// Signed preview links generated in the admin (drafts, scheduled campaigns).
Route::get('/preview/pages/{page}', [PageController::class, 'preview'])
    ->middleware('signed')
    ->name('pages.preview');

// Reference page for the public design system (local development only).
if (app()->isLocal()) {
    Route::view('/styleguide', 'site.styleguide')->name('styleguide');
}

// Admin portal (Vue SPA). Every /admin URL returns the shell; the client router resolves it.
Route::get('/admin/{any?}', SpaController::class)->where('any', '.*')->name('admin');

// Pages built in the admin. Keep these last: the slug route catches everything else.
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/{slug}', [PageController::class, 'slug'])
    ->where('slug', trim(PageSaveRequest::SLUG_PATTERN, '/^$'))
    ->name('page');
