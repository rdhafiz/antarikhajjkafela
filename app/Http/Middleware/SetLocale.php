<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen language (stored in a cookie) to the request.
 * URLs are the same for every language; only the content changes.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->cookie(self::COOKIE);

        app()->setLocale($this->locales->isSupported($chosen) ? $chosen : $this->locales->default());

        return $next($request);
    }
}
