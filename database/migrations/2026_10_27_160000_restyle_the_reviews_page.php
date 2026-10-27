<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The reviews listing takes the home page's look: a soft lilac shade with
 * floating shapes, and the banner line speaks of travellers as well as
 * pilgrims. Only a listing still on the default background and the original
 * banner line change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('pages')->where('system_key', 'reviews')->whereNull('deleted_at')->first();
        if (! $page) {
            return;
        }

        $banner = DB::table('page_sections')->where('page_id', $page->id)->where('component', 'page_banner')->whereNull('deleted_at')->first();
        $content = $banner ? (json_decode($banner->content ?? '[]', true) ?: []) : [];

        if (($content['intro']['en'] ?? null) === 'In the words of the pilgrims who travelled with us.') {
            $content['intro'] = ['en' => 'In the words of the travellers and pilgrims who went with us.', 'bn' => 'আমাদের সাথে সফর করা ভ্রমণকারী ও হাজীদের নিজের কথায়।'];
            DB::table('page_sections')->where('id', $banner->id)->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
        }

        $section = DB::table('page_sections')->where('page_id', $page->id)->where('component', 'page_listing')->whereNull('deleted_at')->first();

        if (! $section) {
            return;
        }

        $style = json_decode($section->style ?? '[]', true) ?: [];

        if (($style['background'] ?? 'default') === 'default' && ($style['decor'] ?? 'none') === 'none') {
            DB::table('page_sections')->where('id', $section->id)->update([
                'style' => json_encode(['background' => 'lilac-soft', 'decor' => 'shapes'] + $style),
            ]);
        }
    }

    public function down(): void
    {
        // The new look stays; admins can change the listing's style in the page builder.
    }
};
