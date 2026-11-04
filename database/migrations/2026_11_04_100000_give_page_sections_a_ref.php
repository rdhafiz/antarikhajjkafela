<?php

use App\PageBuilder\SectionStyle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every section gets its own reference, which campaign buttons point to when
 * they jump to a section on the page (see SectionStyle::newRef()).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')->orderBy('id')->select(['id', 'style'])->each(function ($section) {
            $style = json_decode((string) $section->style, true) ?: [];

            if (empty($style['ref'])) {
                $style['ref'] = SectionStyle::newRef();
                DB::table('page_sections')->where('id', $section->id)->update(['style' => json_encode($style, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        });
    }

    public function down(): void
    {
        // The references are harmless where nothing points to them.
    }
};
