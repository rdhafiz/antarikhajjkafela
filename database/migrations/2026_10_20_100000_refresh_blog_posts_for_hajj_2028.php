<?php

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\SlugRedirect;
use App\PageBuilder\ContentBlocks;
use App\Support\Dates;
use Database\Seeders\BlogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Brings existing sites up to date for Hajj 2028 (October 2026): rewrites the
 * seeded blog posts from BlogSeeder::posts() (the Umrah 2025 news becomes the
 * Hajj 2028 registration news; its old address redirects), and replaces the
 * seeded FAQ and campaign wording and the policy date line only where they
 * are still exactly as seeded, so admin edits stay untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->refreshPosts();
        $this->refreshPageText();

        Cache::forget('sitemap.xml');
    }

    public function down(): void
    {
        // Content update: there is nothing to roll back to.
    }

    private function refreshPosts(): void
    {
        $blocks = app(ContentBlocks::class);

        foreach (app(BlogSeeder::class)->posts() as $data) {
            $post = Post::where('slug', $data['slug'])->first()
                ?? (isset($data['replaces']) ? Post::where('slug', $data['replaces'])->first() : null);

            if (! $post) {
                continue;
            }

            $oldSlug = $post->slug;

            $post->forceFill([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'tags' => $data['tags'],
                'blocks' => $blocks->sanitize($data['blocks']),
                'published_at' => Carbon::parse($data['published_at']),
                'is_featured' => $data['is_featured'] ?? false,
            ])->save();

            if ($oldSlug !== $data['slug']) {
                SlugRedirect::remember('post', $oldSlug, $post->id);
            }
        }
    }

    private function refreshPageText(): void
    {
        $replace = [
            '<p>Bring a copy of your NID or passport, an active mobile number and the government pre-registration fee. Our team completes the rest.</p>' => '<p>We are now registering pilgrims for Hajj 2028. Bring a copy of your NID or passport, an active mobile number and your bank account details; we complete the government pre-registration as soon as the Ministry opens the Hajj 2028 window.</p>',
            '<p>জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি, সক্রিয় মোবাইল নম্বর ও সরকারি প্রাক-নিবন্ধন ফি নিয়ে আসুন। বাকিটা আমাদের টিম সম্পন্ন করবে।</p>' => '<p>আমরা এখন হজ ২০২৮-এর নিবন্ধন নিচ্ছি। জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি, সক্রিয় মোবাইল নম্বর ও ব্যাংক হিসাবের তথ্য নিয়ে আসুন; মন্ত্রণালয় হজ ২০২৮-এর নিবন্ধন চালু করলেই সরকারি প্রাক-নিবন্ধন আমরা সম্পন্ন করব।</p>',
            'Pre-registration is open' => 'Hajj 2028 registration is open',
            'প্রাক-নিবন্ধন চলছে' => 'হজ ২০২৮-এর নিবন্ধন চলছে',
            'Register for Hajj today.' => 'Register for Hajj 2028 today.',
            'আজই হজের নিবন্ধন করুন।' => 'আজই হজ ২০২৮-এর নিবন্ধন করুন।',
            'Government pre-registration fee: BDT 30,000' => 'Government pre-registration deposit, as the Ministry announces it',
            'সরকারি প্রাক-নিবন্ধন ফি: ৩০,০০০ টাকা' => 'মন্ত্রণালয়ের ঘোষণা অনুযায়ী সরকারি প্রাক-নিবন্ধন জমা',
        ];

        PageSection::query()->each(function (PageSection $section) use ($replace) {
            $content = $section->content ?? [];

            array_walk_recursive($content, function (&$value) use ($replace) {
                if (! is_string($value)) {
                    return;
                }

                if (isset($replace[$value])) {
                    $value = $replace[$value];
                } elseif (preg_match('/^সর্বশেষ হালনাগাদ ([A-Z][a-z]+ \d{4})$/', $value, $match)) {
                    // The seeded policy pages wrote the month in English on Bangla pages.
                    $value = 'সর্বশেষ হালনাগাদ '.Dates::format(Carbon::parse('1 '.$match[1]), 'F Y', 'bn');
                }
            });

            if ($content !== $section->content) {
                $section->forceFill(['content' => $content])->save();
            }
        });

        $campaign = Page::where('slug', 'hajj-pre-registration')->first();

        if ($campaign && $campaign->getTranslation('title', 'en', false) === 'Hajj pre-registration') {
            $campaign->setTranslations('title', ['en' => 'Hajj 2028 pre-registration', 'bn' => 'হজ ২০২৮ প্রাক-নিবন্ধন'])->save();
        }
    }
};
