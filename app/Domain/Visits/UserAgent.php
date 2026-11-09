<?php

namespace App\Domain\Visits;

class UserAgent
{
    private const BOT = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|embedly|preview|curl|wget|python-requests|python-urllib|go-http-client|okhttp|java\/|libwww|httpclient|headlesschrome|phantomjs|lighthouse|pingdom|uptime|monitor|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|gptbot|claudebot|ccbot/i';

    private const TABLET = '/ipad|tablet|kindle|silk|playbook|android(?!.*mobile)/i';

    private const PHONE = '/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone/i';

    /** Order matters: iOS UAs say "like Mac OS X". */
    private const PLATFORMS = [
        'iOS' => '/iphone|ipad|ipod/i',
        'Android' => '/android/i',
        'Windows' => '/windows/i',
        'macOS' => '/macintosh|mac os x/i',
        'Linux' => '/linux/i',
    ];

    /** Order matters: Edge and Opera also say "Chrome" and "Safari". */
    private const BROWSERS = [
        'Edge' => '/edg(e|a|ios)?\//i',
        'Opera' => '/opr\/|opera/i',
        'Samsung Internet' => '/samsungbrowser/i',
        'UC Browser' => '/ucbrowser/i',
        'Firefox' => '/firefox|fxios/i',
        'Chrome' => '/chrome|crios/i',
        'Safari' => '/safari/i',
        'IE' => '/msie|trident/i',
    ];

    /** @return array{is_bot: bool, device_type: ?string, platform: ?string, browser: ?string} */
    public static function parse(?string $ua): array
    {
        $ua = trim((string) $ua);

        if ($ua === '' || preg_match(self::BOT, $ua)) {
            return ['is_bot' => true, 'device_type' => null, 'platform' => null, 'browser' => null];
        }

        return [
            'is_bot' => false,
            'device_type' => preg_match(self::TABLET, $ua) ? 'tablet' : (preg_match(self::PHONE, $ua) ? 'phone' : 'desktop'),
            'platform' => self::firstMatch(self::PLATFORMS, $ua),
            'browser' => self::firstMatch(self::BROWSERS, $ua),
        ];
    }

    private static function firstMatch(array $map, string $ua): ?string
    {
        foreach ($map as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return null;
    }
}
