<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The home page's service sections follow the agency's priority: Hajj &
 * Umrah, air tickets, visa processing, then tours. The four sections swap
 * places among the positions they already hold; every other section stays
 * where it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('pages')->where('system_key', 'home')->whereNull('deleted_at')->first();

        if (! $page) {
            return;
        }

        $sections = DB::table('page_sections')->where('page_id', $page->id)->whereNull('deleted_at')->orderBy('sort')->orderBy('id')->get();

        $find = fn (callable $match) => $sections->first(fn ($section) => $match($section->component, json_decode($section->content ?? '[]', true) ?: []));

        $ordered = collect([
            $find(fn ($component) => $component === 'image_text'),
            $find(fn ($component) => $component === 'lead_form'),
            $find(fn ($component, $content) => $component === 'features' && ($content['cta']['url'] ?? null) === '/visa'),
            $find(fn ($component) => $component === 'package_showcase'),
        ])->filter()->values();

        $slots = $ordered->pluck('sort')->sort()->values();

        foreach ($ordered as $i => $section) {
            DB::table('page_sections')->where('id', $section->id)->update(['sort' => $slots[$i]]);
        }
    }

    public function down(): void
    {
        // The order stays; admins can drag sections back in the page builder.
    }
};
