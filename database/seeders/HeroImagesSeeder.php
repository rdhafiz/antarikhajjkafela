<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Database\Seeders\Concerns\ImportsSeedImages;
use Illuminate\Database\Seeder;

/**
 * Background photos for the top of every page, from Unsplash (free to use
 * under the Unsplash License; credits in docs/content/image-credits.md).
 * Only empty image slots are filled.
 */
class HeroImagesSeeder extends Seeder
{
    use ImportsSeedImages;

    /**
     * Page banner photo per page (slug => [file, English alt, Bangla alt]).
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const BANNERS = [
        'services' => ['makkah-hotels.webp', 'Hotels around the Haram in Makkah', 'মক্কায় হারামের আশপাশের হোটেল'],
        'reviews' => ['green-dome.webp', 'The Green Dome of the Prophet\'s Mosque', 'মসজিদে নববীর সবুজ গম্বুজ'],
        'terms-and-conditions' => ['nabawi-colonnade.webp', 'A colonnade of the Prophet\'s Mosque in Madinah', 'মসজিদে নববীর স্তম্ভসারি'],
        'privacy-policy' => ['nabawi-colonnade.webp', 'A colonnade of the Prophet\'s Mosque in Madinah', 'মসজিদে নববীর স্তম্ভসারি'],
    ];

    /**
     * Full-width hero photo for pages that open with a hero section.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const HEROES = [
        'home' => ['home-haram.webp', 'Masjid al-Haram and the Kaaba in Makkah', 'মক্কায় মসজিদুল হারাম ও কাবা'],
        'hajj-pre-registration' => ['hajj-campaign.webp', 'Pilgrims in ihram before the Kaaba', 'কাবার সামনে ইহরাম পরিহিত হাজিগণ'],
    ];

    public function run(): void
    {
        foreach (Page::with('sections')->get() as $page) {
            $key = $page->system_key === 'home' ? 'home' : $page->slug;
            $first = $page->sections->sortBy('sort')->first();

            if ($first?->component === 'page_banner' && isset(self::BANNERS[$key])) {
                [$file, $en, $bn] = self::BANNERS[$key];
                $this->fillSectionImage($first, 'image', fn () => $this->importImage("heroes/{$file}", 'banner', [$en, $bn]));
            }

            if ($first?->component === 'hero' && isset(self::HEROES[$key])) {
                [$file, $en, $bn] = self::HEROES[$key];
                // The photo goes behind the text, so the hero switches to its centered layout.
                $this->fillSectionImage($first, 'image', fn () => $this->importImage("heroes/{$file}", 'hero', [$en, $bn]), ['variant' => 'centered']);
            }
        }

        $this->travelImages();

        $this->postBanners();

        // The closing call-to-action banner gets a photo that moves behind it.
        $cta = Page::where('system_key', 'home')->first()?->sections()->where('component', 'cta')->first();
        if ($cta && ($cta->content['variant'] ?? 'banner') === 'banner') {
            $this->fillSectionImage($cta, 'image', fn () => $this->importImage('heroes/haram-wide.webp', 'banner', ['Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারাম']));
        }

        if (blank(settings('branding.default_banner'))) {
            settings()->set('branding', [
                'default_banner' => $this->importImage('heroes/haram-wide.webp', 'banner', ['Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারাম'])->id,
            ]);
        }
    }

    /**
     * Blog posts without a banner get a photo that fits their subject.
     */
    private function postBanners(): void
    {
        $banners = [
            'how-to-perform-umrah' => ['hajj-regular.webp', 'Pilgrims in the courtyard of Masjid al-Haram', 'মসজিদুল হারামের আঙিনায় হাজিগণ'],
            'important-information-for-hajj-pilgrims' => ['makkah-hotels.webp', 'Hotels around the Haram in Makkah', 'মক্কায় হারামের আশপাশের হোটেল'],
            'umrah-2025-november-group' => ['madinah-golden.webp', 'The Prophet\'s Mosque in Madinah at sunset', 'সূর্যাস্তে মদিনার মসজিদে নববী'],
        ];

        foreach ($banners as $slug => [$file, $en, $bn]) {
            Post::where('slug', $slug)->whereNull('banner_id')->first()
                ?->update(['banner_id' => $this->importImage("heroes/{$file}", 'general', [$en, $bn])->id]);
        }
    }

    private function travelImages(): void
    {
        $crowd = fn () => $this->importImage('heroes/hajj-crowd.webp', 'banner', ['Hajj pilgrims around the Kaaba', 'কাবার চারপাশে হজযাত্রীগণ']);
        $wide = fn () => $this->importImage('heroes/haram-wide.webp', 'banner', ['Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারাম']);

        Service::where('slug', 'hajj')->whereNull('image_id')->first()?->update(['image_id' => $crowd()->id]);
        Service::where('slug', 'umrah')->whereNull('image_id')->first()?->update(['image_id' => $wide()->id]);

        $packageBanners = [
            'hajj-premium-package' => ['hajj-premium.webp', 'Pilgrims performing tawaf around the Kaaba', 'কাবা তাওয়াফরত হাজিগণ'],
            'hajj-regular-package' => ['hajj-regular.webp', 'Pilgrims in the courtyard of Masjid al-Haram', 'মসজিদুল হারামের আঙিনায় হাজিগণ'],
        ];

        foreach (Package::with('service')->whereNull('banner_id')->get() as $package) {
            $media = isset($packageBanners[$package->slug])
                ? $this->importImage("heroes/{$packageBanners[$package->slug][0]}", 'banner', array_slice($packageBanners[$package->slug], 1))
                : ($package->service?->slug === 'hajj' ? $crowd() : $wide());

            $package->update(['banner_id' => $media->id]);
        }
    }
}
