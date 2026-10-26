<?php

namespace App\Console\Commands;

use App\Domain\Menus\MenuTreeSaver;
use App\Domain\Settings\Settings;
use App\Models\Menu;
use App\Models\Post;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use Database\Seeders\HeroImagesSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\SeoSeeder;
use Database\Seeders\TourSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * One-time switch of a live site from the Hajj agency to Antarik Travel
 * International: name and SEO defaults, the bundled new logo, the Tours
 * service, the rebuilt general pages, the Air Tickets and Visa pages and the
 * new menus. Bookings, inquiries, the Hajj and Umrah services and packages,
 * the blog, gallery and reviews are kept. Replaced sections and menu items
 * go to the trash, so nothing is lost.
 */
#[Signature('site:rebrand {--force : Run without asking}')]
#[Description('Switch the site to Antarik Travel International (pages, menus, tours, name and logo)')]
class RebrandSite extends Command
{
    /** Pages rebuilt from the seeder (system key or slug). */
    private const PAGES = ['home', 'about', 'services', 'contact', 'air-tickets', 'visa', 'terms-and-conditions', 'privacy-policy'];

    public function handle(Settings $settings, ComponentRegistry $registry, ContentProcessor $processor, MenuTreeSaver $menus): int
    {
        if (! $this->option('force') && ! $this->confirm('Replace the home, about, services, contact and policy pages and the menus with the travel agency content?')) {
            return self::FAILURE;
        }

        $this->settings($settings);

        $this->callSeeder(TourSeeder::class);
        $this->line('Tours service and sample packages ready.');

        $seeder = app(PageSeeder::class);
        foreach ($seeder->pages() as $definition) {
            if (in_array($definition['system_key'] ?? $definition['slug'], self::PAGES, true)) {
                $seeder->save($definition, $registry, $processor, replace: true);
                $this->line('Rebuilt page: '.$definition['title']['en']);
            }
        }

        foreach (app(MenuSeeder::class)->menus() as $location => $items) {
            $menus->save(Menu::firstOrCreate(['location' => $location]), $items);
        }
        $this->line('Menus replaced.');

        Post::where('author_name->en', 'Antarik Hajj Kafela')->get()
            ->each(fn (Post $post) => $post->update(['author_name' => ['en' => 'Antarik Travel International', 'bn' => 'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল']]));

        $this->callSeeder(HeroImagesSeeder::class);
        $this->callSeeder(SeoSeeder::class);
        Cache::forget('sitemap.xml');

        $this->info('Done. Review the sample tour prices under Travel > Packages before announcing the new site.');

        return self::SUCCESS;
    }

    /**
     * The new name and search defaults; uploaded logos, the share image and
     * the default banner are cleared so the new bundled logo and photos are used.
     */
    private function settings(Settings $settings): void
    {
        $defaults = fn (string $group, array $keys) => collect($keys)
            ->mapWithKeys(fn ($key) => [$key => config("settings.groups.{$group}.fields.{$key}.default")])
            ->all();

        $settings->set('branding', [
            ...$defaults('branding', ['site_name', 'short_description']),
            'logo' => null,
            'app_icon' => null,
            'favicon' => null,
            'default_banner' => null,
        ]);
        $settings->set('seo', [...$defaults('seo', ['meta_title', 'meta_description']), 'og_image' => null]);

        $this->line('Name, description, logo and search defaults updated.');
    }

    private function callSeeder(string $class): void
    {
        $this->callSilently('db:seed', ['--class' => $class, '--force' => true]);
    }
}
