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
     * Back to the page the switch was used on (the `to` path the link carries),
     * else the previous page; only ever a page on this site.
     */
    private function safeReturnUrl(Request $request): string
    {
        $to = $request->query('to');

        if (is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\')) {
            return url($to);
        }

        $previous = url()->previous();
        $sameSite = parse_url($previous, PHP_URL_HOST) === $request->getHost();

        // Never back to a language switch link itself (it would redirect in a loop).
        return $sameSite && ! str_starts_with((string) parse_url($previous, PHP_URL_PATH), '/language/') ? $previous : url('/');
    }
}
