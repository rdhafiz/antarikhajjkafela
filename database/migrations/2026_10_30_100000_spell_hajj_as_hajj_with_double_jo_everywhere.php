<?php

use App\Domain\Menus\MenuRepository;
use App\Domain\Settings\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The previous migration missed saved text on the live server, where the JSON
 * columns keep Bangla as \u escapes. This one rewrites হজ to হজ্জ in the stored
 * text itself, in both forms, so it works however the database stores JSON.
 * সহজ ("easy") stays; reviews are left alone (the customers' own words).
 */
return new class extends Migration
{
    /** @var array<string, string> pattern => replacement */
    private const REPLACEMENTS = [
        '/(?<!স)হজ(?!্জ)/u' => 'হজ্জ',
        '/(?<!\\\\u09b8)\\\\u09b9\\\\u099c(?!\\\\u09cd\\\\u099c)/i' => '\\u09b9\\u099c\\u09cd\\u099c',
    ];

    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'page_sections' => ['content'],
        'pages' => ['title', 'seo'],
        'services' => ['name', 'summary', 'headline', 'highlight', 'description', 'seo'],
        'packages' => ['name', 'summary', 'flights', 'sections', 'seo'],
        'package_types' => ['name', 'short_description'],
        'package_prices' => ['title', 'short_description', 'hotels'],
        'posts' => ['title', 'excerpt', 'author_name', 'tags', 'blocks', 'seo'],
        'post_categories' => ['name'],
        'menu_items' => ['label'],
        'gallery_albums' => ['title', 'description'],
        'gallery_items' => ['caption'],
        'media' => ['alt'],
        'settings' => ['value'],
    ];

    public function up(): void
    {
        $changed = 0;

        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->orderBy('id')->select(['id', ...$columns])->each(function ($row) use ($table, $columns, &$changed) {
                $changes = [];

                foreach ($columns as $column) {
                    $raw = $row->{$column};

                    if (! is_string($raw)) {
                        continue;
                    }

                    $fixed = preg_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $raw);

                    // Only write text that changed and is still valid JSON.
                    if (is_string($fixed) && $fixed !== $raw && json_validate($fixed)) {
                        $changes[$column] = $fixed;
                    }
                }

                if ($changes) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                    $changed++;
                }
            });
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            echo "  Hajj spelling fixed in {$changed} rows.".PHP_EOL;
        }

        app(Settings::class)->flush();
        app(MenuRepository::class)->flushAll();
        Cache::forget('sitemap.xml');
    }

    public function down(): void
    {
        // The old spelling was a mistake; nothing to put back.
    }
};
