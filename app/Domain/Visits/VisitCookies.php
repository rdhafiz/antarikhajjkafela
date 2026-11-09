<?php

namespace App\Domain\Visits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VisitCookies
{
    public const VISITOR = 'pv_vid';

    public const SESSION = 'pv_sid';

    /** @return array{visitor_id: ?string, session_id: ?string} */
    public static function ids(Request $request): array
    {
        return [
            'visitor_id' => self::uuid($request->cookie(self::VISITOR)),
            'session_id' => self::uuid($request->cookie(self::SESSION)),
        ];
    }

    private static function uuid(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
