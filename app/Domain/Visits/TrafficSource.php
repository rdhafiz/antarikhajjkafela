<?php

namespace App\Domain\Visits;

use Illuminate\Http\Request;

class TrafficSource
{
    /** Host (matched on a domain boundary) => network name. */
    private const SOCIAL = [
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook',
        'instagram.com' => 'instagram',
        'messenger.com' => 'messenger',
        't.co' => 'twitter', 'twitter.com' => 'twitter', 'x.com' => 'twitter',
        'linkedin.com' => 'linkedin', 'lnkd.in' => 'linkedin',
        'pinterest.com' => 'pinterest', 'pin.it' => 'pinterest',
        'reddit.com' => 'reddit',
        'tiktok.com' => 'tiktok',
        'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        'whatsapp.com' => 'whatsapp', 'wa.me' => 'whatsapp',
        't.me' => 'telegram', 'telegram.org' => 'telegram',
        'snapchat.com' => 'snapchat',
        'threads.net' => 'threads',
    ];

    /** Regex => engine name; google.* and yandex.* match any TLD. */
    private const SEARCH = [
        '/(^|\.)google\.(com|[a-z]{2}|co\.[a-z]{2}|com\.[a-z]{2})$/' => 'google',
        '/(^|\.)bing\.com$/' => 'bing',
        '/(^|\.)yahoo\.com$/' => 'yahoo',
        '/(^|\.)duckduckgo\.com$/' => 'duckduckgo',
        '/(^|\.)yandex\.(com|[a-z]{2}|co\.[a-z]{2}|com\.[a-z]{2})$/' => 'yandex',
        '/(^|\.)baidu\.com$/' => 'baidu',
        '/(^|\.)ecosia\.org$/' => 'ecosia',
        '/(^|\.)search\.brave\.com$/' => 'brave',
        '/(^|\.)startpage\.com$/' => 'startpage',
    ];

    /** Order matters: messenger and instagram UAs also say FBAN/FBAV. */
    private const IN_APP = [
        'messenger' => '/messenger|fb_iab\/messenger|fban\/messenger/i',
        'instagram' => '/instagram/i',
        'facebook' => '/FBAN|FBAV|FB_IAB|FBIOS/',
        'tiktok' => '/musical_ly|bytedancewebview|tiktok/i',
        'twitter' => '/twitter/i',
        'linkedin' => '/linkedinapp/i',
        'pinterest' => '/pinterest/i',
        'snapchat' => '/snapchat/i',
        'wechat' => '/micromessenger/i',
        'line' => '/line\//i',
    ];

    private const PAID = ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'paid-social', 'cpm', 'cpv', 'display', 'banner', 'retargeting', 'remarketing', 'ads', 'ad'];

    /** @return array{channel: string, source: ?string, referrer_host: ?string, referrer: ?string, in_app: ?string} */
    public static function for(Request $request): array
    {
        $inApp = self::inApp($request->userAgent());
        $referrer = $request->headers->get('referer');
        $host = $referrer ? self::host($referrer) : null;
        $external = $host !== null && ! self::isOwnHost($host, $request);

        $out = fn (string $channel, ?string $source = null) => [
            'channel' => $channel,
            'source' => $source,
            'referrer_host' => $external ? $host : null,
            'referrer' => $external ? $referrer : null,
            'in_app' => $inApp,
        ];

        $socialHost = $external ? self::social($host) : null;
        $searchHost = $external ? self::search($host) : null;

        // 1. UTM tags are believed first.
        $medium = strtolower(trim(self::str($request->query('utm_medium'))));
        $utmSource = strtolower(trim(self::str($request->query('utm_source'))));
        if ($medium !== '' || $utmSource !== '') {
            $named = self::social($utmSource) ?? self::search($utmSource);
            $isSearch = self::search($utmSource) !== null;
            $source = $utmSource !== '' ? $utmSource : ($socialHost ?? $searchHost ?? $inApp);

            if (in_array($medium, ['email', 'e-mail', 'newsletter'], true)) {
                return $out('email', $source);
            }
            if (in_array($medium, ['sms', 'text'], true)) {
                return $out('sms', $source);
            }
            if (in_array($medium, self::PAID, true)) {
                return $out($isSearch || ($named === null && $searchHost) ? 'paid_search' : 'paid_social', $source);
            }
            if ($named !== null && ! $isSearch || $socialHost || $inApp) {
                return $out('social', $source);
            }
            if ($isSearch || $searchHost) {
                return $out('organic_search', $source);
            }

            return $out($external ? 'referral' : 'other', $source);
        }

        // 2. Google click id. fbclid is deliberately ignored: Facebook adds it to organic shares.
        if (self::str($request->query('gclid')) !== '') {
            return $out('paid_search', 'google');
        }

        // 3. In-app browsers usually send no referrer.
        if ($inApp) {
            return $out('social', $inApp);
        }

        // 4. No (external) referrer.
        if (! $external) {
            return $out('direct');
        }

        if ($socialHost) {
            return $out('social', $socialHost);
        }
        if ($searchHost) {
            return $out('organic_search', $searchHost);
        }

        return $out('referral', $host);
    }

    public static function inApp(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        foreach (self::IN_APP as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return null;
    }

    /** Non-string query values (e.g. ?utm_source[]=x) count as absent. */
    private static function str(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    private static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    /** Ours: same as the request host or the APP_URL host, ignoring a leading www. */
    private static function isOwnHost(string $host, Request $request): bool
    {
        $own = array_filter([
            preg_replace('/^www\./', '', strtolower($request->getHost())),
            ($d = parse_url((string) config('app.url'), PHP_URL_HOST)) ? preg_replace('/^www\./', '', strtolower($d)) : null,
        ]);

        return in_array($host, $own, true);
    }

    private static function social(string $host): ?string
    {
        foreach (self::SOCIAL as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $name;
            }
        }

        // utm_source may be a bare network name ("facebook", "instagram").
        return in_array($host, self::SOCIAL, true) ? $host : null;
    }

    private static function search(string $host): ?string
    {
        foreach (self::SEARCH as $pattern => $name) {
            if (preg_match($pattern, $host)) {
                return $name;
            }
        }

        // utm_source may be a bare engine name ("google", "bing").
        return in_array($host, self::SEARCH, true) ? $host : null;
    }
}
