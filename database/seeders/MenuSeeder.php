<?php

namespace Database\Seeders;

use App\Domain\Menus\MenuLinks;
use App\Domain\Menus\MenuTreeSaver;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Default menus. Only fills a menu that is still empty, so edits made in
 * the admin portal are never overwritten.
 */
class MenuSeeder extends Seeder
{
    public function run(MenuTreeSaver $saver): void
    {
        foreach ($this->menus() as $location => $items) {
            $this->seed($saver, $location, $items);
        }
    }

    /**
     * The default items per location; site:rebrand writes them over the old menus.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function menus(): array
    {
        return [
            'primary' => [
                $this->route('home', 'Home', 'হোম'),
                [...$this->route('hajj', 'Hajj & Umrah', 'হজ ও উমরাহ'), 'children' => [
                    $this->route('hajj', 'Hajj', 'হজ'),
                    $this->route('umrah', 'Umrah', 'উমরাহ'),
                ]],
                $this->page('air-tickets', 'Air Tickets', 'এয়ার টিকেট'),
                $this->page('visa', 'Visa', 'ভিসা'),
                $this->service('tours', 'Tours', 'ট্যুর'),
                $this->route('blog', 'Blog', 'ব্লগ'),
                $this->route('about', 'About', 'আমাদের সম্পর্কে'),
                $this->route('contact', 'Contact', 'যোগাযোগ'),
            ],
            'footer' => [
                $this->route('about', 'About us', 'আমাদের সম্পর্কে'),
                $this->route('services', 'Services', 'সেবাসমূহ'),
                $this->route('gallery', 'Gallery', 'গ্যালারি'),
                // Hidden until the Reviews page is published.
                [...$this->route('reviews', 'Reviews', 'রিভিউ'), 'is_visible' => false],
                $this->route('blog', 'Blog & news', 'ব্লগ ও সংবাদ'),
                $this->route('contact', 'Contact', 'যোগাযোগ'),
                $this->route('booking_status', 'Check booking status', 'বুকিং স্ট্যাটাস দেখুন'),
                $this->url('/terms-and-conditions', 'Terms & conditions', 'শর্তাবলী'),
                $this->url('/privacy-policy', 'Privacy policy', 'গোপনীয়তা নীতি'),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function seed(MenuTreeSaver $saver, string $location, array $items): void
    {
        $menu = Menu::firstOrCreate(['location' => $location]);

        if ($menu->items()->doesntExist()) {
            $saver->save($menu, $items);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function route(string $target, string $en, string $bn): array
    {
        return ['label' => ['en' => $en, 'bn' => $bn], 'link_type' => 'route', 'link_target' => $target];
    }

    /**
     * A page by slug, or its address when the page doesn't exist.
     *
     * @return array<string, mixed>
     */
    private function page(string $slug, string $en, string $bn): array
    {
        $id = Page::where('slug', $slug)->value('id');

        return $id ? ['label' => ['en' => $en, 'bn' => $bn], 'link_type' => MenuLinks::PAGE, 'link_target' => $id] : $this->url("/{$slug}", $en, $bn);
    }

    /**
     * @return array<string, mixed>
     */
    private function service(string $slug, string $en, string $bn): array
    {
        $id = Service::where('slug', $slug)->value('id');

        return $id ? ['label' => ['en' => $en, 'bn' => $bn], 'link_type' => MenuLinks::SERVICE, 'link_target' => $id] : $this->url("/{$slug}", $en, $bn);
    }

    /**
     * @return array<string, mixed>
     */
    private function url(string $url, string $en, string $bn): array
    {
        return ['label' => ['en' => $en, 'bn' => $bn], 'link_type' => 'url', 'url' => $url];
    }
}
