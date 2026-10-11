<?php

namespace Database\Seeders;

use App\Domain\Media\MediaUploader;
use App\Models\GalleryAlbum;
use App\Models\Media;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Photos from the agency's previous website (database/seeders/images):
 * the 2025 Hajj workshop album, About images and the Umrah banner.
 *
 * Images are imported once, and only empty image slots are filled, so
 * anything chosen in the admin portal is never replaced.
 */
class OldSiteMediaSeeder extends Seeder
{
    private const DIR = __DIR__.'/images';

    public function __construct(private readonly MediaUploader $uploader) {}

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
            $media = $this->import("hajj-workshop-2025-{$number}.webp", 'gallery', [$en, $bn]);
            $album->items()->create(['type' => 'image', 'media_id' => $media->id, 'caption' => ['en' => $en, 'bn' => $bn]]);
        }

        $album->update(['cover_id' => $this->import('hajj-workshop-2025-1.webp', 'gallery')->id]);
    }

    private function aboutImages(): void
    {
        $scholar = fn () => $this->import('hajj-workshop-2025-1.webp', 'content', ['A scholar teaching at our Hajj workshop', 'আমাদের হজ কর্মশালায় একজন আলেমের আলোচনা']);
        $team = fn () => $this->import('office-guests.webp', 'card', ['Our team with guests at the office', 'অফিসে অতিথিদের সাথে আমাদের টিম']);
        $audience = fn () => $this->import('hajj-workshop-2025-5.webp', 'content', ['Pilgrims at our Hajj workshop', 'আমাদের হজ কর্মশালায় হাজিগণ']);

        foreach (Page::whereIn('system_key', ['home', 'about'])->get() as $page) {
            foreach ($page->sections()->where('component', 'about')->get() as $section) {
                $this->fillImage($section, 'image', $scholar);
                $this->fillImage($section, 'image_secondary', $team);
            }
        }

        $about = Page::where('system_key', 'about')->first();

        foreach ($about?->sections()->where('component', 'image_text')->get() ?? [] as $section) {
            $this->fillImage($section, 'image', $audience);
        }
    }

    private function umrahImages(): void
    {
        $alt = ['Pilgrims praying before the Kaaba', 'কাবার সামনে দোয়ারত হাজিগণ'];

        $service = Service::where('slug', 'umrah')->whereNull('image_id')->first();
        $service?->update(['image_id' => $this->import('kaaba-prayer.webp', 'banner', $alt)->id]);

        foreach (Package::whereNull('banner_id')->whereHas('service', fn ($q) => $q->where('slug', 'umrah'))->get() as $package) {
            $package->update(['banner_id' => $this->import('kaaba-prayer.webp', 'banner', $alt)->id]);
        }

        Post::where('slug', 'what-is-umrah')->whereNull('banner_id')->first()
            ?->update(['banner_id' => $this->import('kaaba-prayer.webp', 'general', $alt)->id]);
    }

    /**
     * @param  callable(): Media  $media
     */
    private function fillImage(PageSection $section, string $field, callable $media): void
    {
        $content = $section->content ?? [];

        if (! empty($content[$field])) {
            return;
        }

        $content[$field] = $media()->id;
        $section->update(['content' => $content]);
    }

    /**
     * Import a seed image for a usage once; later calls reuse it.
     *
     * @param  array{0: string, 1: string}|null  $alt
     */
    private function import(string $file, string $usage, ?array $alt = null): Media
    {
        $existing = Media::where('original_name', $file)->where('usage', $usage)->first();

        if ($existing) {
            return $existing;
        }

        $copy = tempnam(sys_get_temp_dir(), 'seed');
        copy(self::DIR.'/'.$file, $copy);

        try {
            $media = $this->uploader->upload(new UploadedFile($copy, $file, 'image/webp', null, true), $usage);
        } finally {
            @unlink($copy);
        }

        if ($alt) {
            $media->update(['alt' => ['en' => $alt[0], 'bn' => $alt[1]]]);
        }

        return $media;
    }
}
