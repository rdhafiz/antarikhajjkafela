<?php

namespace Database\Seeders;

use App\Models\GalleryAlbum;
use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Database\Seeders\Concerns\ImportsSeedImages;
use Illuminate\Database\Seeder;

/**
 * Photos from the agency's previous website (database/seeders/images):
 * the 2025 Hajj workshop album, About images and the Umrah banner.
 *
 * Images are imported once, and only empty image slots are filled, so
 * anything chosen in the admin portal is never replaced.
 */
class OldSiteMediaSeeder extends Seeder
{
    use ImportsSeedImages;

    public function run(): void
    {
        $this->workshopAlbum();
        $this->aboutImages();
        $this->umrahImages();
    }

    private function workshopAlbum(): void
    {
        if (GalleryAlbum::where('slug', 'hajj-workshop-2025')->exists()) {
            return;
        }

        $album = GalleryAlbum::create([
            'title' => ['en' => 'Hajj workshop 2025', 'bn' => 'হজ প্রশিক্ষণ কর্মশালা ২০২৫'],
            'slug' => 'hajj-workshop-2025',
            // The date shown on the workshop banner in the photos.
            'event_date' => '2025-04-19',
            'description' => [
                'en' => 'Our scholars preparing pilgrims for Hajj: the rituals step by step, the supplications, and practical advice for the journey.',
                'bn' => 'হজের জন্য হাজিদের প্রস্তুত করছেন আমাদের আলেমগণ: ধাপে ধাপে আমল, দোয়া এবং সফরের ব্যবহারিক পরামর্শ।',
            ],
            'is_published' => true,
        ]);

        $captions = [
            1 => ['A session of the Hajj workshop', 'হজ কর্মশালার একটি অধিবেশন'],
            2 => ['Explaining the rituals of Hajj', 'হজের আমলসমূহের ব্যাখ্যা'],
            3 => ['Guidance from our scholars', 'আমাদের আলেমদের দিকনির্দেশনা'],
            4 => ['Questions and answers for first-time pilgrims', 'প্রথমবার হজযাত্রীদের প্রশ্নোত্তর'],
            5 => ['Pilgrims attending the workshop', 'কর্মশালায় অংশ নেওয়া হাজিগণ'],
        ];

        // Added oldest first so the album shows photo 1 first (items are newest first).
        foreach (array_reverse($captions, true) as $number => [$en, $bn]) {
            $media = $this->importImage("hajj-workshop-2025-{$number}.webp", 'gallery', [$en, $bn]);
            $album->items()->create(['type' => 'image', 'media_id' => $media->id, 'caption' => ['en' => $en, 'bn' => $bn]]);
        }

        $album->update(['cover_id' => $this->importImage('hajj-workshop-2025-1.webp', 'gallery')->id]);
    }

    private function aboutImages(): void
    {
        $scholar = fn () => $this->importImage('hajj-workshop-2025-1.webp', 'content', ['A scholar teaching at our Hajj workshop', 'আমাদের হজ কর্মশালায় একজন আলেমের আলোচনা']);
        $team = fn () => $this->importImage('office-guests.webp', 'card', ['Our team with guests at the office', 'অফিসে অতিথিদের সাথে আমাদের টিম']);
        $audience = fn () => $this->importImage('hajj-workshop-2025-5.webp', 'content', ['Pilgrims at our Hajj workshop', 'আমাদের হজ কর্মশালায় হাজিগণ']);

        foreach (Page::whereIn('system_key', ['home', 'about'])->get() as $page) {
            foreach ($page->sections()->where('component', 'about')->get() as $section) {
                $this->fillSectionImage($section, 'image', $scholar);
                $this->fillSectionImage($section, 'image_secondary', $team);
            }
        }

        $about = Page::where('system_key', 'about')->first();

        foreach ($about?->sections()->where('component', 'image_text')->get() ?? [] as $section) {
            $this->fillSectionImage($section, 'image', $audience);
        }
    }

    private function umrahImages(): void
    {
        $alt = ['Pilgrims praying before the Kaaba', 'কাবার সামনে দোয়ারত হাজিগণ'];

        $service = Service::where('slug', 'umrah')->whereNull('image_id')->first();
        $service?->update(['image_id' => $this->importImage('kaaba-prayer.webp', 'banner', $alt)->id]);

        foreach (Package::whereNull('banner_id')->whereHas('service', fn ($q) => $q->where('slug', 'umrah'))->get() as $package) {
            $package->update(['banner_id' => $this->importImage('kaaba-prayer.webp', 'banner', $alt)->id]);
        }

        Post::where('slug', 'what-is-umrah')->whereNull('banner_id')->first()
            ?->update(['banner_id' => $this->importImage('kaaba-prayer.webp', 'general', $alt)->id]);
    }
}
