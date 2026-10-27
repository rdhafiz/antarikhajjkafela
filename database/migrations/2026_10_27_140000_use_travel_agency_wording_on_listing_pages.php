<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The blog, gallery and reviews pages kept wording from before the agency
 * became a travel agency: a Hajj-only closing banner and Hajj-only intros.
 * They take the general travel wording the other pages use. Text an admin
 * has changed is left alone (only the original English text is matched).
 */
return new class extends Migration
{
    private const OLD_CTA = 'Ready to answer the call? Let us plan your Hajj or Umrah together.';

    private const CTA = [
        'eyebrow' => ['en' => 'Plan your next trip', 'bn' => 'পরবর্তী সফরের পরিকল্পনা করুন'],
        'title' => ['en' => 'Tell us where you want to go. We will take care of the rest.', 'bn' => 'কোথায় যেতে চান বলুন, বাকিটা আমরা দেখব।'],
        'text' => ['en' => 'Air tickets, visas, tours, Hajj and Umrah: one team, honest advice and clear prices.', 'bn' => 'এয়ার টিকেট, ভিসা, ট্যুর, হজ ও উমরাহ—একটি টিম, আন্তরিক পরামর্শ ও স্পষ্ট মূল্য।'],
    ];

    /**
     * Old English intro => new intro.
     *
     * @var array<string, array{en: string, bn: string}>
     */
    private const INTROS = [
        'Guidance, news and updates for Hajj and Umrah pilgrims.' => ['en' => 'Travel tips, visa updates and guidance for Hajj and Umrah pilgrims.', 'bn' => 'ভ্রমণ টিপস, ভিসার হালনাগাদ এবং হজ ও উমরাহ যাত্রীদের জন্য দিকনির্দেশনা।'],
        'Moments from our Hajj and Umrah journeys, workshops and gatherings.' => ['en' => 'Moments from our tours, Hajj and Umrah journeys, workshops and gatherings.', 'bn' => 'আমাদের ট্যুর, হজ ও উমরাহ সফর, কর্মশালা ও আয়োজনের কিছু মুহূর্ত।'],
    ];

    public function up(): void
    {
        DB::table('page_sections')->whereIn('component', ['cta', 'page_listing', 'page_banner'])->whereNull('deleted_at')->orderBy('id')->each(function ($section) {
            $content = json_decode($section->content ?? '[]', true) ?: [];

            if ($section->component === 'cta' && ($content['title']['en'] ?? null) === self::OLD_CTA) {
                $content = array_replace($content, self::CTA);
            } elseif (isset(self::INTROS[$content['intro']['en'] ?? ''])) {
                $content['intro'] = self::INTROS[$content['intro']['en']];
            } else {
                return;
            }

            DB::table('page_sections')->where('id', $section->id)->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
        });
    }

    public function down(): void
    {
        // The travel wording stays; admins can edit it in the page builder.
    }
};
