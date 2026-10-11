<?php

namespace Tests\Feature;

use Tests\TestCase;

class OldSiteRedirectsTest extends TestCase
{
    public function test_old_website_addresses_redirect_permanently(): void
    {
        $this->get('/about-us')->assertStatus(301)->assertRedirect('/about');
        $this->get('/services/hajj-premium-package')->assertStatus(301)->assertRedirect('/packages/hajj-premium-package');
        $this->get('/blog/I45nGtwtO2MI')->assertStatus(301)->assertRedirect('/blog/how-to-perform-umrah');
        $this->get('/news-briefings')->assertStatus(301)->assertRedirect('/blog?category=news');
    }

    public function test_every_redirect_points_to_a_local_path(): void
    {
        foreach (config('redirects') as $from => $to) {
            $this->assertStringStartsWith('/', $to, $from);
            $this->assertStringNotContainsString('//', $to, $from);
        }
    }
}
