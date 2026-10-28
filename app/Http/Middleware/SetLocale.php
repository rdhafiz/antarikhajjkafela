<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen language (stored in a cookie) to the request.
 * URLs are the same for every language; only the content changes. Without a
 * choice (first visits, search engines) the page is in the site's default
 * language, chosen by the admin in Settings → Languages.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    /** The visitor's language: their choice, else the site's default language. Also used for error pages. */
    public function resolve(Request $request): string
    {
        $chosen = $request->cookie(self::COOKIE);

        return $this->locales->isSupported($chosen) ? $chosen : $this->locales->default();
    }
}
