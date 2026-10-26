<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentBlocks;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Blog categories and the articles and news from the agency's previous
 * website, plus the live review, gallery and blog sections on the home page.
 */
class BlogSeeder extends Seeder
{
    public function run(ContentBlocks $blocks): void
    {
        if (! PostCategory::exists()) {
            $this->seedPosts($blocks);
        }

        $this->addHomeSections();
    }

    private function seedPosts(ContentBlocks $blocks): void
    {
        $categories = [
            'guides' => PostCategory::create(['name' => $this->t('Guides', 'গাইড'), 'slug' => 'guides', 'sort' => 0])->id,
            'news' => PostCategory::create(['name' => $this->t('News & briefings', 'সংবাদ ও বিজ্ঞপ্তি'), 'slug' => 'news', 'sort' => 1])->id,
        ];
        $author = $this->t('Antarik Travel International', 'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল');

        foreach ($this->posts() as $data) {
            Post::create([
                ...collect($data)->except(['category', 'replaces'])->all(),
                'category_id' => $categories[$data['category']],
                'author_name' => $author,
                'blocks' => $blocks->sanitize($data['blocks']),
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => Carbon::parse($data['published_at']),
                'is_featured' => $data['is_featured'] ?? false,
                'seo' => ['title' => [], 'description' => [], 'image' => null, 'noindex' => false],
            ]);
        }
    }

    /**
     * The articles and news (database/seeders/content/blog-posts.php). The
     * blog refresh migrations copy them onto existing sites; 'replaces' is an
     * older slug the post took over.
     *
     * @return list<array<string, mixed>>
     */
    public function posts(): array
    {
        return require __DIR__.'/content/blog-posts.php';
    }

    /**
     * Reviews, gallery and latest posts on the home page. Each section stays
     * hidden until its module has content.
     */
    private function addHomeSections(): void
    {
        $home = Page::where('system_key', 'home')->first();

        if (! $home || $home->sections()->whereIn('component', ['testimonials', 'gallery_preview', 'latest_posts'])->exists()) {
            return;
        }

        $sections = [
            'testimonials' => [
                'after' => 'process_steps',
                'background' => 'white',
                'content' => [
                    'eyebrow' => $this->t('Pilgrim stories', 'হাজিদের কথা'),
                    'title' => $this->t('In the words of our pilgrims', 'আমাদের হাজিদের মুখে'),
                    'source' => 'featured',
                    'limit' => 3,
                    'cta' => ['url' => '/reviews', 'label' => $this->t('Read all reviews', 'সব রিভিউ পড়ুন')],
                ],
            ],
            'gallery_preview' => [
                'after' => 'testimonials',
                'background' => 'navy-deep',
                'content' => [
                    'eyebrow' => $this->t('Gallery', 'গ্যালারি'),
                    'title' => $this->t('Moments from our journeys', 'আমাদের সফরের মুহূর্ত'),
                    'limit' => '5',
                    'cta' => ['url' => '/gallery', 'label' => $this->t('Open the gallery', 'গ্যালারি দেখুন')],
                ],
            ],
            'latest_posts' => [
                'after' => 'faq',
                'background' => 'white',
                'content' => [
                    'eyebrow' => $this->t('Guides & news', 'গাইড ও সংবাদ'),
                    'title' => $this->t('Prepare for your journey', 'সফরের প্রস্তুতি নিন'),
                    'limit' => 4,
                    'layout' => 'magazine',
                    'cta' => ['url' => '/blog', 'label' => $this->t('All articles', 'সব লেখা')],
                ],
            ],
        ];

        $registry = app(ComponentRegistry::class);
        $processor = app(ContentProcessor::class);

        foreach ($sections as $key => $section) {
            $after = $home->sections()->where('component', $section['after'])->max('sort')
                ?? $home->sections()->where('component', '!=', 'cta')->max('sort')
                ?? 0;
            $home->sections()->where('sort', '>', $after)->increment('sort');

            $home->sections()->create([
                'component' => $key,
                'sort' => $after + 1,
                'content' => $processor->sanitize($registry->get($key)->fields(), $section['content']),
                'style' => SectionStyle::sanitize(['background' => $section['background']]),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $en, string $bn): array
    {
        return ['type' => 'text', 'body' => $this->t($en, $bn)];
    }

    /**
     * @return array{en: string, bn: string}
     */
    private function t(string $en, string $bn): array
    {
        return ['en' => $en, 'bn' => $bn];
    }
}
