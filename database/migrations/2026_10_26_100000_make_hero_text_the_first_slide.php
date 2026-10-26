<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The centered hero is now a list of slides (each an image with text or an
 * image only). A hero's own text and image used to be its first slide, so
 * they become slide one here and the page looks the same as before.
 */
return new class extends Migration
{
    private const FIRST_SLIDE = ['image', 'eyebrow', 'title', 'highlight', 'text', 'primary', 'secondary'];

    public function up(): void
    {
        DB::table('page_sections')->where('component', 'hero')->orderBy('id')->each(function ($section) {
            $content = json_decode($section->content ?? '[]', true) ?: [];

            if (! in_array($content['variant'] ?? null, ['centered', 'slider'], true)) {
                return;
            }

            $first = array_intersect_key($content, array_flip(self::FIRST_SLIDE));
            $hasText = collect(['title', 'highlight', 'eyebrow', 'text'])->contains(fn ($key) => filled(array_filter((array) ($first[$key] ?? []))));

            if (! $hasText && empty($first['image'])) {
                return;
            }

            $content['variant'] = 'centered';
            $content['slides'] = [
                ['_id' => Str::random(10), 'type' => 'text', 'image_mobile' => null, 'link' => null, ...$first],
                ...($content['slides'] ?? []),
            ];

            DB::table('page_sections')->where('id', $section->id)->update(['content' => json_encode($content)]);
        });
    }

    public function down(): void
    {
        // The first slide stays a slide; the hero still renders the same.
    }
};
