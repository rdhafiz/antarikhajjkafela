<?php

namespace Tests\Unit\Visits;

use App\Domain\Visits\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAgentTest extends TestCase
{
    public static function cases(): array
    {
        $bot = ['is_bot' => true, 'device_type' => null, 'platform' => null, 'browser' => null];

        return [
            'empty' => [null, $bot],
            'blank' => ['', $bot],
            'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', $bot],
            'facebook crawler' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', $bot],
            'iphone safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                ['is_bot' => false, 'device_type' => 'phone', 'platform' => 'iOS', 'browser' => 'Safari']],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                ['is_bot' => false, 'device_type' => 'tablet', 'platform' => 'iOS', 'browser' => 'Safari']],
            'android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                ['is_bot' => false, 'device_type' => 'tablet', 'platform' => 'Android', 'browser' => 'Chrome']],
            'windows edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0',
                ['is_bot' => false, 'device_type' => 'desktop', 'platform' => 'Windows', 'browser' => 'Edge']],
            'mac chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                ['is_bot' => false, 'device_type' => 'desktop', 'platform' => 'macOS', 'browser' => 'Chrome']],
            'samsung internet' => ['Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0 Mobile Safari/537.36',
                ['is_bot' => false, 'device_type' => 'phone', 'platform' => 'Android', 'browser' => 'Samsung Internet']],
        ];
    }

    #[DataProvider('cases')]
    public function test_parse(?string $ua, array $expected): void
    {
        $this->assertSame($expected, UserAgent::parse($ua));
    }
}
