<?php

use App\Domain\Menus\MenuRepository;
use App\Domain\Settings\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hajj is written হজ্জ in Bangla. Saved content (pages, services, packages,
 * posts, menus, gallery, image alt texts, settings) still had the short হজ,
 * so it is rewritten everywhere. সহজ ("easy") is a different word and stays.
 * Reviews are left alone: they are the customers' own words.
 */
return new class extends Migration
{
    private const PATTERN = '/(?<!স)হজ(?!্জ)/u';

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
        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->orderBy('id')->select(['id', ...$columns])->each(function ($row) use ($table, $columns) {
                $changes = [];

                foreach ($columns as $column) {
                    $raw = $row->{$column};

                    if (! is_string($raw) || ! str_contains($raw, 'হজ') && ! str_contains($raw, 'হজ')) {
                        continue;
                    }

                    $value = json_decode($raw, true);
                    $fixed = $this->respell($value);

                    if ($fixed !== $value) {
                        $changes[$column] = json_encode($fixed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }

                if ($changes) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            });
        }

        app(Settings::class)->flush();
        app(MenuRepository::class)->flushAll();
        Cache::forget('sitemap.xml');
    }

    public function down(): void
    {
        // The old spelling was a mistake; nothing to put back.
    }

    private function respell(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_replace(self::PATTERN, 'হজ্জ', $value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->respell($item), $value);
        }

        return $value;
    }
};
