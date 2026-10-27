<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Service;
use Database\Seeders\Concerns\ImportsSeedImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Photos for the top of every page, the home slides and image sections, from
 * Unsplash (free to use under the Unsplash License; credits in
 * docs/content/image-credits.md). Only empty image slots are filled.
 */
class HeroImagesSeeder extends Seeder
{
    use ImportsSeedImages;

    private const AIRPORT = ['travel/airport.webp', 'Travellers in a bright airport terminal', 'উজ্জ্বল এয়ারপোর্ট টার্মিনালে ভ্রমণকারীরা'];

    private const SKY = ['travel/airplane-sky.webp', 'An airliner wing above the clouds', 'মেঘের ওপরে উড়োজাহাজের ডানা'];

    private const TAKEOFF = ['travel/airplane-takeoff.webp', 'An airliner lifting off the runway', 'রানওয়ে থেকে উড্ডয়নরত উড়োজাহাজ'];

    private const PASSPORT = ['travel/passport-visa.webp', 'Passports ready for a visa application', 'ভিসা আবেদনের জন্য প্রস্তুত পাসপোর্ট'];

    /**
     * Page banner photo per page (slug => [path, English alt, Bangla alt]).
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const BANNERS = [
        'services' => self::AIRPORT,
        'air-tickets' => self::TAKEOFF,
        'visa' => self::PASSPORT,
        'reviews' => ['heroes/green-dome.webp', 'The Green Dome of the Prophet\'s Mosque', 'মসজিদে নববীর সবুজ গম্বুজ'],
        'terms-and-conditions' => self::AIRPORT,
        'privacy-policy' => self::AIRPORT,
    ];

    /**
     * Slide photos, in order, for pages that open with a hero section.
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private const HERO_SLIDES = [
        // The agency slide, then Hajj & Umrah, air tickets, visa and tours.
        'home' => [
            ['travel/panorama.webp', 'The Kaaba, world landmarks and an airliner above a globe', 'কাবা, বিশ্বের দর্শনীয় স্থাপনা ও গ্লোবের ওপর উড়োজাহাজ'],
            ['heroes/home-haram.webp', 'Masjid al-Haram and the Kaaba in Makkah', 'মক্কায় মসজিদুল হারাম ও কাবা'],
            self::TAKEOFF,
            self::PASSPORT,
            ['travel/maldives.webp', 'Overwater villas above a turquoise lagoon in the Maldives', 'মালদ্বীপে ফিরোজা লেগুনের ওপর ওয়াটার ভিলা'],
        ],
        'hajj-pre-registration' => [['heroes/hajj-campaign.webp', 'Pilgrims in ihram before the Kaaba', 'কাবার সামনে ইহরাম পরিহিত হাজিগণ']],
    ];

    /**
     * Card photos for feature items, by their English title (service cards,
     * visa regions).
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const ITEM_PHOTOS = [
        'Hajj & Umrah' => ['heroes/haram-wide.webp', 'Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারাম'],
        'Air tickets' => self::TAKEOFF,
        'Visa processing' => self::PASSPORT,
        'Tour packages' => ['travel/maldives.webp', 'Overwater villas above a turquoise lagoon in the Maldives', 'মালদ্বীপে ফিরোজা লেগুনের ওপর ওয়াটার ভিলা'],
        'Middle East' => ['travel/dubai.webp', 'The Burj Khalifa above the Dubai skyline', 'দুবাইয়ের স্কাইলাইনের ওপরে বুর্জ খলিফা'],
        'Asia' => ['travel/singapore.webp', 'Marina Bay Sands and the Singapore skyline at night', 'রাতে মেরিনা বে স্যান্ডস ও সিঙ্গাপুরের স্কাইলাইন'],
        'Europe & UK' => ['travel/europe.webp', 'The Eiffel Tower in Paris', 'প্যারিসের আইফেল টাওয়ার'],
        'North America & Oceania' => ['travel/sydney.webp', 'The Sydney Opera House and Harbour Bridge', 'সিডনি অপেরা হাউস ও হারবার ব্রিজ'],
    ];

    public function run(): void
    {
        $this->replaceAirTicketPhotos();

        foreach (Page::with('sections')->get() as $page) {
            $key = $page->system_key === 'home' ? 'home' : $page->slug;
            $first = $page->sections->sortBy('sort')->first();

            if ($first?->component === 'page_banner' && isset(self::BANNERS[$key])) {
                $this->fillSectionImage($first, 'image', fn () => $this->photo(self::BANNERS[$key], 'banner'));
            }

            if ($first?->component === 'hero' && isset(self::HERO_SLIDES[$key])) {
                $this->slideImages($first, self::HERO_SLIDES[$key]);
            }

            $this->imageSections($key, $page->sections);
            $this->itemImages($page->sections);
        }

        $this->travelImages();

        $this->postBanners();

        // The closing call-to-action banner gets a photo that moves behind it.
        $cta = Page::where('system_key', 'home')->first()?->sections()->where('component', 'cta')->first();
        if ($cta && ($cta->content['variant'] ?? 'banner') === 'banner') {
            $this->fillSectionImage($cta, 'image', fn () => $this->photo(self::SKY, 'banner'));
        }

        if (blank(settings('branding.default_banner'))) {
            settings()->set('branding', ['default_banner' => $this->photo(self::SKY, 'banner')->id]);
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $photo
     */
    private function photo(array $photo, string $usage): Media
    {
        return $this->importImage($photo[0], $usage, [$photo[1], $photo[2]]);
    }

    /**
     * Slides without an image get their photo. A split hero without an image
     * becomes one full-width slide, its text over the photo.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $photos
     */
    private function slideImages(PageSection $hero, array $photos): void
    {
        $content = $hero->fresh()->content ?? [];
        $changed = false;

        if (($content['variant'] ?? 'split') === 'split') {
            if (! empty($content['image'])) {
                return;
            }

            $content['variant'] = 'centered';
            $content['slides'] = [['_id' => Str::random(10), 'type' => 'text', 'image_mobile' => null, 'link' => null, ...Arr::only($content, ['eyebrow', 'title', 'highlight', 'text', 'primary', 'secondary']), 'image' => null]];
        }

        foreach ($content['slides'] ?? [] as $i => $slide) {
            if (empty($slide['image']) && empty($slide['image_mobile']) && isset($photos[$i])) {
                $content['slides'][$i]['image'] = $this->photo($photos[$i], 'hero')->id;
                $changed = true;
            }
        }

        if ($changed) {
            $hero->update(['content' => $content]);
        }
    }

    /**
     * Image-and-text sections: the Hajj & Umrah section on the home and about
     * pages and the documents section on the Visa page.
     *
     * @param  iterable<PageSection>  $sections
     */
    private function imageSections(string $key, iterable $sections): void
    {
        $photo = match ($key) {
            'home' => ['heroes/haram-courtyard.webp', 'Pilgrims around the Kaaba in Masjid al-Haram', 'মসজিদুল হারামে কাবার চারপাশে হাজিগণ'],
            'about' => ['heroes/madinah-golden.webp', 'The Prophet\'s Mosque in Madinah at sunset', 'সূর্যাস্তে মদিনার মসজিদে নববী'],
            'visa' => self::PASSPORT,
            default => null,
        };

        if (! $photo) {
            return;
        }

        foreach ($sections as $section) {
            if ($section->component === 'image_text') {
                $this->fillSectionImage($section, 'image', fn () => $this->photo($photo, 'content'));
            }
        }
    }

    /**
     * Feature items without an image get the photo for their title.
     *
     * @param  iterable<PageSection>  $sections
     */
    private function itemImages(iterable $sections): void
    {
        foreach ($sections as $section) {
            if ($section->component !== 'features') {
                continue;
            }

            $content = $section->fresh()->content ?? [];
            $changed = false;

            foreach ($content['items'] ?? [] as $i => $item) {
                $photo = self::ITEM_PHOTOS[$item['title']['en'] ?? ''] ?? null;

                if ($photo && empty($item['image'])) {
                    $content['items'][$i]['image'] = $this->photo($photo, 'card')->id;
                    $changed = true;
                }
            }

            if ($changed) {
                $section->update(['content' => $content]);
            }
        }
    }

    /**
     * The air tickets slide, banner and service card first had a terminal or
     * a distant plane; the take-off photo replaces them where the old seeded
     * photo is still in place (a photo chosen in the admin portal stays).
     */
    private function replaceAirTicketPhotos(): void
    {
        $old = Media::whereIn('original_name', ['airport.webp', 'airplane-sky.webp'])->pluck('id')->all();
        $isOld = fn ($id) => $id && in_array((int) $id, $old, true);

        foreach (PageSection::whereIn('component', ['hero', 'page_banner', 'features'])->with('page')->get() as $section) {
            $content = $section->content ?? [];
            $changed = false;

            if ($section->component === 'hero') {
                foreach ($content['slides'] ?? [] as $i => $slide) {
                    if (($slide['eyebrow']['en'] ?? null) === 'Air tickets' && $isOld($slide['image'] ?? null)) {
                        $content['slides'][$i]['image'] = $this->photo(self::TAKEOFF, 'hero')->id;
                        $changed = true;
                    }
                }
            } elseif ($section->component === 'page_banner') {
                if ($section->page?->slug === 'air-tickets' && $isOld($content['image'] ?? null)) {
                    $content['image'] = $this->photo(self::TAKEOFF, 'banner')->id;
                    $changed = true;
                }
            } else {
                foreach ($content['items'] ?? [] as $i => $item) {
                    if (($item['title']['en'] ?? null) === 'Air tickets' && $isOld($item['image'] ?? null)) {
                        $content['items'][$i]['image'] = $this->photo(self::TAKEOFF, 'card')->id;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $section->update(['content' => $content]);
            }
        }
    }

    /**
     * Blog posts without a banner get a photo that fits their subject.
     */
    private function postBanners(): void
    {
        $banners = [
            'how-to-perform-umrah' => ['heroes/hajj-regular.webp', 'Pilgrims in the courtyard of Masjid al-Haram', 'মসজিদুল হারামের আঙিনায় হাজিগণ'],
            'important-information-for-hajj-pilgrims' => ['heroes/makkah-hotels.webp', 'Hotels around the Haram in Makkah', 'মক্কায় হারামের আশপাশের হোটেল'],
            'hajj-2028-registration' => ['heroes/madinah-golden.webp', 'The Prophet\'s Mosque in Madinah at sunset', 'সূর্যাস্তে মদিনার মসজিদে নববী'],
        ];

        foreach ($banners as $slug => $photo) {
            Post::where('slug', $slug)->whereNull('banner_id')->first()
                ?->update(['banner_id' => $this->photo($photo, 'general')->id]);
        }
    }

    private function travelImages(): void
    {
        $crowd = fn () => $this->importImage('heroes/hajj-crowd.webp', 'banner', ['Hajj pilgrims around the Kaaba', 'কাবার চারপাশে হজযাত্রীগণ']);
        $wide = fn () => $this->importImage('heroes/haram-wide.webp', 'banner', ['Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারাম']);

        Service::where('slug', 'hajj')->whereNull('image_id')->first()?->update(['image_id' => $crowd()->id]);
        Service::where('slug', 'umrah')->whereNull('image_id')->first()?->update(['image_id' => $wide()->id]);

        $packageBanners = [
            'hajj-premium-package' => ['heroes/hajj-premium.webp', 'Pilgrims performing tawaf around the Kaaba', 'কাবা তাওয়াফরত হাজিগণ'],
            'hajj-regular-package' => ['heroes/hajj-regular.webp', 'Pilgrims in the courtyard of Masjid al-Haram', 'মসজিদুল হারামের আঙিনায় হাজিগণ'],
        ];

        // Tour packages bring their own photo (TourSeeder); only Hajj and Umrah packages are filled here.
        foreach (Package::with('service')->whereNull('banner_id')->whereHas('service', fn ($q) => $q->whereIn('slug', ['hajj', 'umrah']))->get() as $package) {
            $media = isset($packageBanners[$package->slug])
                ? $this->photo($packageBanners[$package->slug], 'banner')
                : ($package->service?->slug === 'hajj' ? $crowd() : $wide());

            $package->update(['banner_id' => $media->id]);
        }
    }
}
