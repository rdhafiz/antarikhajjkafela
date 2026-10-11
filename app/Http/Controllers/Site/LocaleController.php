<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale, Locales $locales): RedirectResponse
    {
        abort_unless($locales->isSupported($locale), 404);

        $cookie = cookie()->forever(SetLocale::COOKIE, $locale, httpOnly: false);

        return redirect()->to($this->safeReturnUrl($request))->withCookie($cookie);
    }

    /**
     * Only redirect back to a page on this site.
     */
    private function safeReturnUrl(Request $request): string
    {
        $previous = url()->previous();

        return str_starts_with($previous, url('/')) ? $previous : url('/');
    }
}
