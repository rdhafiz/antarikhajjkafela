<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The About, Services, Contact, Air Tickets and Visa pages take the home page's look: soft shades,
 * floating shapes and colourful icons. Only sections that still have the
 * background they were created with change, so admin choices stay.
 */
return new class extends Migration
{
    /**
     * page system key or slug => list of [component, layout or null, old background, new background, colourful icons].
     *
     * @var array<string, list<array{0: string, 1: ?string, 2: string, 3: string, 4: bool}>>
     */
    private const CHANGES = [
        'about' => [
            ['about', null, 'white', 'white', false],
            ['features', 'tiles', 'sand', 'sky-soft', true],
            ['features', 'cards', 'white', 'mint-soft', true],
            ['image_text', null, 'sand', 'peach-soft', false],
            ['features', 'list', 'navy-deep', 'navy-deep', false],
            ['team', null, 'white', 'lilac-soft', false],
        ],
        'services' => [
            ['features', 'cards', 'sand', 'white', true],
            ['features', 'tiles', 'white', 'sky-soft', true],
            ['process_steps', null, 'sand', 'lilac-soft', false],
        ],
        'contact' => [
            ['contact_form', 'concierge', 'sand', 'sky-soft', false],
            ['faq', null, 'white', 'lilac-soft', false],
        ],
        'air-tickets' => [
            ['lead_form', null, 'sand', 'sky-soft', false],
            ['features', 'cards', 'white', 'white', true],
            ['faq', null, 'sand', 'mint-soft', false],
        ],
        'visa' => [
            ['features', 'cards', 'white', 'white', true],
            ['features', 'photos', 'sand', 'mint-soft', true],
            ['image_text', null, 'white', 'peach-soft', false],
            ['process_steps', null, 'sand', 'lilac-soft', false],
            ['lead_form', null, 'white', 'sky-soft', false],
            ['faq', null, 'sand', 'white', false],
        ],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $key => $changes) {
            // Default pages by their system key; Air Tickets and Visa are custom pages, found by slug.
            $page = DB::table('pages')->where(fn ($q) => $q->where('system_key', $key)->orWhere('slug', $key))->whereNull('deleted_at')->first();

            if (! $page) {
                continue;
            }

            $sections = DB::table('page_sections')->where('page_id', $page->id)->whereNull('deleted_at')->orderBy('sort')->get();

            foreach ($changes as [$component, $layout, $from, $to, $colorful]) {
                $section = $sections->first(function ($section) use ($component, $layout, $from) {
                    $content = json_decode($section->content ?? '[]', true) ?: [];
                    $style = json_decode($section->style ?? '[]', true) ?: [];

                    return $section->component === $component
                        && ($layout === null || ($content['layout'] ?? null) === $layout)
                        && ($style['background'] ?? null) === $from
                        && ($style['decor'] ?? 'none') === 'none';
                });

                if (! $section) {
                    continue;
                }

                $content = json_decode($section->content ?? '[]', true) ?: [];
                $style = json_decode($section->style ?? '[]', true) ?: [];

                if ($colorful && empty($content['icon_style'])) {
                    $content['icon_style'] = 'colorful';
                }

                DB::table('page_sections')->where('id', $section->id)->update([
                    'style' => json_encode(['background' => $to, 'decor' => 'shapes'] + $style),
                    'content' => json_encode($content),
                ]);

                $sections = $sections->reject(fn ($s) => $s->id === $section->id);
            }
        }
    }

    public function down(): void
    {
        // The new look stays; admins can change each section's style in the page builder.
    }
};
