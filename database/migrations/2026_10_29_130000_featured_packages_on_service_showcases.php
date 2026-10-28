<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Package sections that show one service's packages (the home page's tours)
 * now show only its featured packages, so "Featured" decides what the home
 * page shows. Sections that already have the setting keep it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')->where('component', 'package_showcase')->orderBy('id')->each(function ($row) {
            $content = json_decode($row->content ?? '[]', true) ?: [];

            if (($content['source'] ?? null) !== 'service' || array_key_exists('featured_only', $content)) {
                return;
            }

            $content['featured_only'] = true;
            DB::table('page_sections')->where('id', $row->id)->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
        });
    }

    public function down(): void
    {
        // The setting is harmless to keep.
    }
};
