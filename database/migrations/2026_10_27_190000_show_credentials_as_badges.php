<?php

use Database\Seeders\HeroImagesSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The "Licensed & accredited" section becomes certificate cards with the
 * licence and membership logos, and gains HAAB and Biman Bangladesh Airlines.
 * Only sections still in the original icon-list style change; their own
 * wording stays, the two new items are added in order, and the logos are
 * filled where empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sections = DB::table('page_sections')->where('component', 'features')->whereNull('deleted_at')->get();
        $all = collect(PageSeeder::credentialItems(fn (string $en, string $bn) => ['en' => $en, 'bn' => $bn]));
        $changedAny = false;

        foreach ($sections as $section) {
            $content = json_decode($section->content ?? '[]', true) ?: [];
            $titles = collect($content['items'] ?? [])->map(fn ($item) => $item['title']['en'] ?? null);

            if (($content['layout'] ?? null) !== 'list' || ! $titles->contains('IATA accredited')) {
                continue;
            }

            // Keep the existing items (and their edits), insert the new ones where they belong.
            $items = collect($content['items']);
            foreach ($all as $position => $item) {
                if ($titles->contains($item['title']['en'])) {
                    continue;
                }

                $items->splice(min($position, $items->count()), 0, [['_id' => Str::random(10), 'image' => null, 'logo' => null, ...$item]]);
            }

            $content['items'] = $items->values()->all();
            $content['layout'] = 'badges';
            $content['columns'] = '3';
            $content['intro'] = array_filter($content['intro'] ?? []) ?: ['en' => 'Government licences and industry memberships you can verify before you travel with us.', 'bn' => 'আমাদের সাথে সফরের আগেই যাচাই করে নিতে পারেন এমন সরকারি লাইসেন্স ও সদস্যপদ।'];

            DB::table('page_sections')->where('id', $section->id)->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
            $changedAny = true;
        }

        if ($changedAny) {
            (new HeroImagesSeeder)->fillCredentialLogos();
        }
    }

    public function down(): void
    {
        // The badges stay; the style can be changed back in the page builder.
    }
};
