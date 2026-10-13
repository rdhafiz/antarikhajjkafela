<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin portal is in English, so its API answers (validation errors,
 * sign-in failures) in the base language whatever the site's default is.
 * Content languages are chosen explicitly by the admin controllers.
 */
class UseBaseLocale
{
    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->locales->base());

        return $next($request);
    }
}
