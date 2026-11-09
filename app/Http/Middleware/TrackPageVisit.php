<?php

namespace App\Http\Middleware;

use App\Domain\Visits\VisitRecorder;
use Closure;
use Illuminate\Http\Request;

/** Records one visit per rendered public page; controllers opt in through Visited. */
class TrackPageVisit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $visited = $request->attributes->get('visited');

        $countable = $visited !== null
            && $request->isMethod('GET')
            && ! $request->ajax()
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')
            && ! auth()->check();

        if ($countable) {
            foreach (app(VisitRecorder::class)->record($request, $visited['type'], $visited['id'] ?? null) as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }

        return $response;
    }
}
