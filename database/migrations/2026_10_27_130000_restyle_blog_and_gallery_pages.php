<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The blog and gallery listings take the home page's look: a soft shade with
 * floating shapes. Only listings still on the default background change.
 */
return new class extends Migration
{
    private const SHADES = ['blog' => 'sky-soft', 'gallery' => 'mint-soft'];

    public function up(): void
    {
        foreach (self::SHADES as $key => $shade) {
            $page = DB::table('pages')->where('system_key', $key)->whereNull('deleted_at')->first();
            $section = $page ? DB::table('page_sections')->where('page_id', $page->id)->where('component', 'page_listing')->whereNull('deleted_at')->first() : null;

            if (! $section) {
                continue;
            }

            $style = json_decode($section->style ?? '[]', true) ?: [];

            if (in_array($style['background'] ?? 'default', ['default', null], true) && ($style['decor'] ?? 'none') === 'none') {
                DB::table('page_sections')->where('id', $section->id)->update([
                    'style' => json_encode(['background' => $shade, 'decor' => 'shapes'] + $style),
                ]);
            }
        }
    }

    public function down(): void
    {
        // The new look stays; admins can change the listing's style in the page builder.
    }
};
