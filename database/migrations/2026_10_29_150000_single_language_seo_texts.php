<?php

use App\Domain\Settings\Settings;
use App\Support\Locales;
use App\Support\Seo\SeoText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEO titles and descriptions (each page, service, package and post, and the
 * site-wide defaults) become one text in whichever language the admin types,
 * instead of an English and a Bangla version. The value kept is the one in the
 * site's default language, else whichever was filled.
 */
return new class extends Migration
{
    private const TABLES = ['pages', 'services', 'packages', 'posts'];

    public function up(): void
    {
        $locale = app(Locales::class)->default();

        foreach (self::TABLES as $table) {
            DB::table($table)->whereNotNull('seo')->orderBy('id')->each(function ($row) use ($table, $locale) {
                $seo = json_decode($row->seo, true) ?: [];

                foreach (['title', 'description'] as $key) {
                    if (array_key_exists($key, $seo)) {
                        $seo[$key] = SeoText::single($seo[$key], $locale);
                    }
                }

                DB::table($table)->where('id', $row->id)->update(['seo' => json_encode($seo, JSON_UNESCAPED_UNICODE)]);
            });
        }

        foreach (['meta_title', 'meta_description'] as $key) {
            $setting = DB::table('settings')->where('group', 'seo')->where('key', $key)->first();

            if ($setting) {
                $value = SeoText::single(json_decode($setting->value ?? 'null', true), $locale);
                DB::table('settings')->where('id', $setting->id)->update(['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
            }
        }

        app(Settings::class)->flush();
    }

    public function down(): void
    {
        // The single texts stay readable: they are shown as they are in every language.
    }
};
