<?php

use App\Models\Post;
use App\Models\SlugRedirect;
use App\PageBuilder\ContentBlocks;
use Database\Seeders\BlogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The seeded blog posts in their full length (each reads in five minutes or
 * more), copied from BlogSeeder::posts() onto existing sites.
 */
return new class extends Migration
{
    public function up(): void
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

        Cache::forget('sitemap.xml');
    }

    public function down(): void
    {
        // Content update: there is nothing to roll back to.
    }
};
