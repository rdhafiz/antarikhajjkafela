<?php

namespace Tests\Unit\Visits;

use App\Domain\Visits\TrafficSource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrafficSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://antarik.test']);
    }

    private function req(array $query = [], ?string $referrer = null, ?string $ua = null, string $host = 'antarik.test'): Request
    {
        $server = ['HTTP_HOST' => $host];
        if ($referrer) {
            $server['HTTP_REFERER'] = $referrer;
        }
        if ($ua) {
            $server['HTTP_USER_AGENT'] = $ua;
        }

        return Request::create("https://$host/article/x", 'GET', $query, [], [], $server);
    }

    public function test_facebook_in_app_without_referrer_is_social(): void
    {
        $r = TrafficSource::for($this->req(ua: 'Mozilla/5.0 (iPhone) [FBAN/FBIOS;FBAV/400]'));
        $this->assertSame('social', $r['channel']);
        $this->assertSame('facebook', $r['source']);
        $this->assertSame('facebook', $r['in_app']);
    }

    public function test_instagram_and_messenger_in_app(): void
    {
        $this->assertSame('instagram', TrafficSource::inApp('Mozilla/5.0 Instagram 300.0'));
        $this->assertSame('messenger', TrafficSource::inApp('Mozilla/5.0 [FBAN/MessengerForiOS;FBAV/400]'));
        $this->assertNull(TrafficSource::inApp('Mozilla/5.0 Chrome/120'));
        $this->assertNull(TrafficSource::inApp(null));
    }

    public function test_own_host_referrer_is_direct(): void
    {
        $r = TrafficSource::for($this->req(referrer: 'https://www.antarik.test/packages'));
        $this->assertSame('direct', $r['channel']);
        $this->assertNull($r['referrer']);
        $this->assertNull($r['referrer_host']);
    }

    public function test_app_url_host_counts_as_own_host(): void
    {
        $r = TrafficSource::for($this->req(referrer: 'https://antarik.test/hajj', host: 'www.antarik.test'));
        $this->assertSame('direct', $r['channel']);
        $this->assertNull($r['referrer']);
    }

    public function test_no_referrer_is_direct(): void
    {
        $r = TrafficSource::for($this->req());
        $this->assertSame(['channel' => 'direct', 'source' => null, 'referrer_host' => null, 'referrer' => null, 'in_app' => null], $r);
    }

    public function test_utm_cpc_facebook_is_paid_social(): void
    {
        $r = TrafficSource::for($this->req(['utm_medium' => 'cpc', 'utm_source' => 'Facebook']));
        $this->assertSame(['paid_social', 'facebook'], [$r['channel'], $r['source']]);
    }

    public function test_utm_cpc_google_is_paid_search(): void
    {
        $r = TrafficSource::for($this->req(['utm_medium' => 'cpc', 'utm_source' => 'google']));
        $this->assertSame(['paid_search', 'google'], [$r['channel'], $r['source']]);
    }

    public function test_utm_newsletter_is_email(): void
    {
        $r = TrafficSource::for($this->req(['utm_medium' => 'newsletter', 'utm_source' => 'weekly']));
        $this->assertSame(['email', 'weekly'], [$r['channel'], $r['source']]);
    }

    public function test_gclid_is_paid_search_google(): void
    {
        $r = TrafficSource::for($this->req(['gclid' => 'abc']));
        $this->assertSame(['paid_search', 'google'], [$r['channel'], $r['source']]);
    }

    public function test_fbclid_alone_is_not_paid(): void
    {
        $r = TrafficSource::for($this->req(['fbclid' => 'abc'], 'https://l.facebook.com/l.php?u=x'));
        $this->assertSame(['social', 'facebook', 'l.facebook.com'], [$r['channel'], $r['source'], $r['referrer_host']]);
    }

    public function test_google_referrer_is_organic_search(): void
    {
        $r = TrafficSource::for($this->req(referrer: 'https://www.google.com.bd/search?q=x'));
        $this->assertSame(['organic_search', 'google', 'google.com.bd'], [$r['channel'], $r['source'], $r['referrer_host']]);
        $this->assertSame('https://www.google.com.bd/search?q=x', $r['referrer']);
    }

    public function test_notgoogle_host_is_referral(): void
    {
        $r = TrafficSource::for($this->req(referrer: 'https://notgoogle.example/a'));
        $this->assertSame(['referral', 'notgoogle.example'], [$r['channel'], $r['source']]);
    }

    public function test_t_co_is_social_twitter(): void
    {
        $r = TrafficSource::for($this->req(referrer: 'https://t.co/abc'));
        $this->assertSame(['social', 'twitter'], [$r['channel'], $r['source']]);
    }

    public static function searchHosts(): array
    {
        return [
            'google.evil.com' => ['https://google.evil.com/', 'referral', 'google.evil.com'],
            'www.google.com.bd' => ['https://www.google.com.bd/', 'organic_search', 'google'],
            'google.co.uk' => ['https://google.co.uk/', 'organic_search', 'google'],
            'yandex.ru' => ['https://yandex.ru/', 'organic_search', 'yandex'],
            'yandex.evil.com' => ['https://yandex.evil.com/', 'referral', 'yandex.evil.com'],
        ];
    }

    #[DataProvider('searchHosts')]
    public function test_search_host_matching(string $referrer, string $channel, string $source): void
    {
        $r = TrafficSource::for($this->req(referrer: $referrer));
        $this->assertSame([$channel, $source], [$r['channel'], $r['source']]);
    }

    public function test_array_query_params_are_ignored(): void
    {
        $r = TrafficSource::for($this->req(['utm_source' => ['x'], 'utm_medium' => ['y'], 'gclid' => ['z']]));
        $this->assertSame('direct', $r['channel']);
    }
}
