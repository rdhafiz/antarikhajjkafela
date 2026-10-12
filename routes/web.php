<?php

use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\BookingController;
use App\Http\Controllers\Site\FormController;
use App\Http\Controllers\Site\GalleryController;
use App\Http\Controllers\Site\LocaleController;
use App\Http\Controllers\Site\ManifestController;
use App\Http\Controllers\Site\PackageController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PaymentCallbackController;
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
Route::get('/preview/posts/{post}', [BlogController::class, 'preview'])
    ->middleware('signed')
    ->name('posts.preview');

// Reference page for the public design system (local development only).
if (app()->isLocal()) {
    Route::view('/styleguide', 'site.styleguide')->name('styleguide');
}

// Admin portal (Vue SPA) at /{admin_prefix}/admin. Every admin URL returns the shell; the client router resolves it.
Route::get(trim(config('app.admin_prefix'), '/').'/admin/{any?}', SpaController::class)->where('any', '.*')->name('admin');

// Packages
Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{slug}', [PackageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('packages.show');

// Booking: form, the customer's private (signed) booking page, and the status lookup.
Route::get('/packages/{slug}/book', [BookingController::class, 'create'])->where('slug', '[a-z0-9-]+')->name('bookings.create');
Route::post('/packages/{slug}/book', [BookingController::class, 'store'])->where('slug', '[a-z0-9-]+')->middleware('throttle:booking')->name('bookings.store');
Route::get('/bookings/{booking:reference}', [BookingController::class, 'show'])->middleware('signed')->name('bookings.show');
Route::post('/bookings/{booking:reference}/pay', [BookingController::class, 'pay'])->middleware(['signed', 'throttle:6,1'])->name('bookings.pay');
Route::get('/booking/status', [BookingController::class, 'status'])->name('bookings.status');
Route::post('/booking/status', [BookingController::class, 'lookup'])->middleware('throttle:booking-lookup')->name('bookings.lookup');

// Payment gateway returns and notifications (CSRF-exempt in bootstrap/app.php; verified with the gateway API).
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/payments/sslcommerz/ipn', [PaymentCallbackController::class, 'sslcommerzIpn'])->name('payments.sslcommerz.ipn');
    Route::post('/payments/sslcommerz/{result}', [PaymentCallbackController::class, 'sslcommerz'])->whereIn('result', ['success', 'fail', 'cancel'])->name('payments.sslcommerz.callback');
    Route::match(['get', 'post'], '/payments/dgepay/return/{transaction}', [PaymentCallbackController::class, 'dgepay'])->where('transaction', '[A-Za-z0-9-]+')->name('payments.dgepay.return');
});

// Blog posts and gallery albums. Their listings live on the Blog and Gallery pages.
Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('blog.show');
Route::get('/gallery/{slug}', [GalleryController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('gallery.show');

// Addresses from the previous website (config/redirects.php).
foreach (config('redirects') as $from => $to) {
    Route::permanentRedirect($from, $to);
}

// Pages built in the admin. Keep these last: the slug route catches everything else.
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/{slug}', [PageController::class, 'slug'])
    ->where('slug', trim(PageSaveRequest::SLUG_PATTERN, '/^$'))
    ->name('page');
