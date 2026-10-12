<?php

namespace Tests\Feature;

use App\Support\Dates;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DatesTest extends TestCase
{
    public function test_dates_follow_the_page_language(): void
    {
        $date = Carbon::parse('2025-08-10 09:00:00');

        $this->assertSame('10 August 2025', Dates::format($date, locale: 'en'));
        $this->assertSame('১০ আগস্ট ২০২৫', Dates::format($date, locale: 'bn'));
        $this->assertSame('আগস্ট ২০২৫', Dates::format($date, 'F Y', 'bn'));
        $this->assertNull(Dates::format(null));

        app()->setLocale('bn');
        $date->locale('en');
        $this->assertSame('১০ আগস্ট ২০২৫', Dates::format($date));
        $this->assertSame('en', $date->locale, 'The original date keeps its own locale.');
    }
}
