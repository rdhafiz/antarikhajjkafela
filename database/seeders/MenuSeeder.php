<?php

namespace Database\Seeders;

use App\Domain\Menus\MenuTreeSaver;
use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Default menus. Only fills a menu that is still empty, so edits made in
 * the admin portal are never overwritten.
 */
class MenuSeeder extends Seeder
{
    public function run(MenuTreeSaver $saver): void
    {
        $this->seed($saver, 'primary', [
            $this->route('home', 'Home', 'হোম'),
            $this->route('hajj', 'Hajj', 'হজ'),
            $this->route('umrah', 'Umrah', 'উমরাহ'),
            $this->route('services', 'Services', 'সেবাসমূহ'),
            $this->route('gallery', 'Gallery', 'গ্যালারি'),
            $this->route('blog', 'Blog', 'ব্লগ'),
            [
                ...$this->route('about', 'About', 'আমাদের সম্পর্কে'),
                'children' => [
                    $this->route('about', 'About us', 'আমাদের সম্পর্কে'),
                    $this->route('reviews', 'Reviews', 'রিভিউ'),
                ],
            ],
            $this->route('contact', 'Contact', 'যোগাযোগ'),
        ]);

        $this->seed($saver, 'footer', [
            $this->route('about', 'About us', 'আমাদের সম্পর্কে'),
            $this->route('services', 'Services', 'সেবাসমূহ'),
            $this->route('blog', 'Blog & news', 'ব্লগ ও সংবাদ'),
            $this->route('contact', 'Contact', 'যোগাযোগ'),
            $this->route('booking_status', 'Check booking status', 'বুকিং স্ট্যাটাস দেখুন'),
            $this->url('/terms-and-conditions', 'Terms & conditions', 'শর্তাবলী'),
            $this->url('/privacy-policy', 'Privacy policy', 'গোপনীয়তা নীতি'),
        ]);
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
     * @return array<string, mixed>
     */
    private function url(string $url, string $en, string $bn): array
    {
        return ['label' => ['en' => $en, 'bn' => $bn], 'link_type' => 'url', 'url' => $url];
    }
}
