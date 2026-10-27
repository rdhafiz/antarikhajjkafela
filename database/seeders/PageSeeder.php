<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Service;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use App\Support\Dates;
use Illuminate\Database\Seeder;

/**
 * Default pages, the Air Tickets and Visa pages and the policy pages of the
 * travel agency (Hajj & Umrah content from the agency's previous website). Only creates pages that don't exist yet, so edits made
 * in the admin portal are never overwritten.
 */
class PageSeeder extends Seeder
{
    public function run(ComponentRegistry $registry, ContentProcessor $processor): void
    {
        foreach ($this->pages() as $definition) {
            $this->save($definition, $registry, $processor);
        }

        $this->translatePolicies($processor, $registry);
    }

    /**
     * Create a page from its definition. An existing page is left alone,
     * unless $replace: then its sections go to the trash and the defined
     * ones take their place (used by site:rebrand).
     *
     * @param  array<string, mixed>  $definition
     */
    public function save(array $definition, ComponentRegistry $registry, ContentProcessor $processor, bool $replace = false): Page
    {
        $page = Page::firstOrNew(
            $definition['system_key'] ? ['system_key' => $definition['system_key']] : ['slug' => $definition['slug']],
        );

        if ($page->exists && ! $replace) {
            return $page;
        }

        if ($page->exists) {
            $page->sections()->get()->each->delete();
            // Search texts and the share image are filled again by SeoSeeder.
            $page->forceFill(['title' => $definition['title'], 'seo' => ['noindex' => (bool) ($page->seo['noindex'] ?? false)]])->save();
        } else {
            $page->fill([
                'type' => $definition['type'],
                'title' => $definition['title'],
                'slug' => $definition['slug'],
                'status' => $definition['status'] ?? Page::STATUS_PUBLISHED,
                'seo' => $definition['seo'] ?? [],
                'focused_layout' => $definition['focused_layout'] ?? false,
            ])->save();
        }

        foreach ($definition['sections'] as $sort => [$component, $content, $style]) {
            $page->sections()->create([
                'component' => $component,
                'sort' => $sort,
                'content' => $processor->sanitize($registry->get($component)->fields(), $content),
                'style' => SectionStyle::sanitize($style),
            ]);
        }

        return $page;
    }

    /**
     * Policy pages created before their Bangla text existed get it now,
     * unless an admin has already written one.
     */
    private function translatePolicies(ContentProcessor $processor, ComponentRegistry $registry): void
    {
        $bangla = ['terms-and-conditions' => $this->termsHtmlBn(), 'privacy-policy' => $this->privacyHtmlBn()];

        foreach ($bangla as $slug => $html) {
            $section = Page::where('slug', $slug)->first()?->sections()->where('component', 'rich_text')->first();

            if (! $section || filled($section->content['body']['bn'] ?? null)) {
                continue;
            }

            $content = $section->content;
            $content['body']['bn'] = $html;
            $section->update(['content' => $processor->sanitize($registry->get('rich_text')->fields(), $content)]);
        }
    }

    /**
     * Every page definition; site:rebrand rebuilds some of them on live sites.
     *
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        $pages = [];

        foreach (config('page-builder.default_pages') as $key => $page) {
            $pages[] = [
                'type' => Page::TYPE_DEFAULT,
                'system_key' => $key,
                'slug' => $page['slug'],
                'title' => $page['title'],
                // Reviews stays a draft until there are real reviews to show.
                'status' => $key === 'reviews' ? Page::STATUS_DRAFT : Page::STATUS_PUBLISHED,
                'sections' => match ($key) {
                    'home' => $this->home(),
                    'about' => $this->about(),
                    'services' => $this->services(),
                    'contact' => $this->contact(),
                    // No banner: the gallery and blog open with their own centered heading.
                    'gallery' => [
                        ['page_listing', [
                            'eyebrow' => $this->t('Our gallery', 'আমাদের গ্যালারি'),
                            'intro' => $this->t('Moments from our tours, Hajj and Umrah journeys, workshops and gatherings.', 'আমাদের ট্যুর, হজ ও উমরাহ সফর, কর্মশালা ও আয়োজনের কিছু মুহূর্ত।'),
                        ], ['background' => 'mint-soft', 'decor' => 'shapes']],
                        $this->cta(),
                    ],
                    'blog' => [
                        ['page_listing', [
                            'eyebrow' => $this->t('Guides & news', 'গাইড ও সংবাদ'),
                            'intro' => $this->t('Travel tips, visa updates and guidance for Hajj and Umrah pilgrims.', 'ভ্রমণ টিপস, ভিসার হালনাগাদ এবং হজ ও উমরাহ যাত্রীদের জন্য দিকনির্দেশনা।'),
                        ], ['background' => 'sky-soft', 'decor' => 'shapes']],
                        $this->cta(),
                    ],
                    default => [
                        $this->banner($page['title'], match ($key) {
                            'reviews' => $this->t('In the words of the travellers and pilgrims who went with us.', 'আমাদের সাথে সফর করা ভ্রমণকারী ও হাজীদের নিজের কথায়।'),
                            default => $this->t('Guidance, news and updates for Hajj and Umrah pilgrims.', 'হজ ও উমরাহ যাত্রীদের জন্য দিকনির্দেশনা, সংবাদ ও আপডেট।'),
                        }),
                        ['page_listing', [], ['background' => 'lilac-soft', 'decor' => 'shapes']],
                        $this->cta(),
                    ],
                },
            ];
        }

        $pages[] = $this->airTickets();
        $pages[] = $this->visa();
        $pages[] = $this->policy('terms-and-conditions', $this->t('Terms & conditions', 'শর্তাবলী'), $this->t($this->termsHtml(), $this->termsHtmlBn()));
        $pages[] = $this->policy('privacy-policy', $this->t('Privacy policy', 'গোপনীয়তা নীতি'), $this->t($this->privacyHtml(), $this->privacyHtmlBn()));
        $pages[] = $this->registrationCampaign();

        return $pages;
    }

    /**
     * @return array<string, string>
     */
    private function t(string $en, string $bn): array
    {
        return ['en' => $en, 'bn' => $bn];
    }

    /**
     * @return array<string, mixed>
     */
    private function link(string $url, string $en, string $bn): array
    {
        return ['url' => $url, 'label' => $this->t($en, $bn)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function banner(array $title, array $intro, ?array $eyebrow = null): array
    {
        return ['page_banner', ['title' => $title, 'intro' => $intro, 'eyebrow' => $eyebrow ?? [], 'breadcrumbs' => true], []];
    }

    private function cta(): array
    {
        return ['cta', [
            'variant' => 'banner',
            'eyebrow' => $this->t('Plan your next trip', 'পরবর্তী সফরের পরিকল্পনা করুন'),
            'title' => $this->t('Tell us where you want to go. We will take care of the rest.', 'কোথায় যেতে চান বলুন, বাকিটা আমরা দেখব।'),
            'text' => $this->t('Air tickets, visas, tours, Hajj and Umrah: one team, honest advice and clear prices.', 'এয়ার টিকেট, ভিসা, ট্যুর, হজ ও উমরাহ—একটি টিম, আন্তরিক পরামর্শ ও স্পষ্ট মূল্য।'),
            'contact_buttons' => true,
        ], []];
    }

    /**
     * The home page: the agency's four services, with Hajj & Umrah as one
     * slide and one section. Slides: the agency, Hajj & Umrah, air tickets,
     * visa processing, tour packages; the service sections follow the same
     * order.
     *
     * @return list<array>
     */
    private function home(): array
    {
        return [
            ['hero', [
                'variant' => 'centered',
                'slides' => [
                    $this->slide('Hajj & Umrah · Air Tickets · Visa Processing · Tour Packages', 'হজ ও উমরাহ · এয়ার টিকেট · ভিসা প্রসেসিং · ট্যুর প্যাকেজ', 'Trusted travel services,', 'বিশ্বস্ত ট্রাভেল সেবা,', 'for every journey you make.', 'আপনার প্রতিটি যাত্রায়।',
                        'From the sacred journey of Hajj and Umrah to air tickets, visas and holidays around the world, one licensed team in Dhaka plans every step with you.',
                        'হজ ও উমরাহর পবিত্র সফর থেকে এয়ার টিকেট, ভিসা ও বিশ্বজুড়ে ছুটির ভ্রমণ—ঢাকার একটি লাইসেন্সপ্রাপ্ত টিম প্রতিটি ধাপ আপনার সাথে সাজিয়ে দেয়।',
                        $this->link('/services', 'Explore our services', 'আমাদের সেবাসমূহ'), $this->link('/air-tickets', 'Get a fare quote', 'ভাড়া জানতে চান')),
                    $this->slide('Hajj & Umrah · Antarik Hajj Kafela', 'হজ ও উমরাহ · আন্তরিক হজ্জ কাফেলা', 'Hajj and Umrah,', 'হজ ও উমরাহ,', 'with scholars beside you.', 'আলেমদের সান্নিধ্যে।',
                        'Our Hajj and Umrah division has guided pilgrims from Bangladesh since 2016: Hajj licence 602, Umrah licence 411.',
                        '২০১৬ সাল থেকে আমাদের হজ ও উমরাহ বিভাগ বাংলাদেশের হাজিদের সেবা দিচ্ছে: হজ লাইসেন্স ৬০২, উমরাহ লাইসেন্স ৪১১।',
                        $this->link('/hajj', 'Hajj packages', 'হজ প্যাকেজ'), $this->link('/umrah', 'Umrah packages', 'উমরাহ প্যাকেজ')),
                    $this->slide('Air tickets', 'এয়ার টিকেট', 'Fly anywhere,', 'যেকোনো গন্তব্যে উড়ুন,', 'at the right fare.', 'সঠিক ভাড়ায়।',
                        'Fares from every major airline compared for you, with date changes and group bookings handled by our ticketing team.',
                        'সব বড় এয়ারলাইনের ভাড়া তুলনা করে দিই; তারিখ পরিবর্তন ও গ্রুপ বুকিং দেখে আমাদের টিকেটিং টিম।',
                        $this->link('/air-tickets', 'Request a quote', 'কোটেশন নিন')),
                    $this->slide('Visa processing', 'ভিসা প্রসেসিং', 'Visas made simple,', 'ভিসা এখন সহজ,', 'from file to stamp.', 'ফাইল থেকে স্ট্যাম্প পর্যন্ত।',
                        'Tourist, business, student and work visas: we check your documents, prepare the file and book the appointment.',
                        'ট্যুরিস্ট, বিজনেস, স্টুডেন্ট ও ওয়ার্ক ভিসা: কাগজপত্র যাচাই, ফাইল প্রস্তুত ও অ্যাপয়েন্টমেন্ট—সব আমরা করি।',
                        $this->link('/visa', 'Visa services', 'ভিসা সেবা')),
                    $this->slide('Tour packages', 'ট্যুর প্যাকেজ', 'Holidays planned', 'ছুটির সফর সাজানো', 'down to the last detail.', 'প্রতিটি খুঁটিনাটিসহ।',
                        'Thailand, Malaysia, Dubai, the Maldives, Türkiye, Europe and more, with flights, hotels and sightseeing in one price.',
                        'থাইল্যান্ড, মালয়েশিয়া, দুবাই, মালদ্বীপ, তুরস্ক, ইউরোপসহ আরও অনেক গন্তব্য—ফ্লাইট, হোটেল ও দর্শনীয় স্থান এক মূল্যে।',
                        $this->link('/tours', 'Explore tours', 'ট্যুর দেখুন')),
                ],
                'highlights' => [
                    ['value' => '2016', 'label' => $this->t('Serving since', 'সেবা দিয়ে আসছি')],
                    ['value' => '1500+', 'label' => $this->t('Journeys arranged', 'আয়োজিত সফর')],
                    ['value' => '14', 'label' => $this->t('Tour destinations', 'ট্যুর গন্তব্য')],
                    ['value' => '4', 'label' => $this->t('Services, one team', 'সেবা, একটি টিম')],
                ],
            ], []],
            ['features', [
                'eyebrow' => $this->t('What we do', 'আমরা যা করি'),
                'title' => $this->t('Four services, one trusted team', 'চারটি সেবা, একটি বিশ্বস্ত টিম'),
                'intro' => $this->t('Whether you are flying for work, a holiday, your studies or the pilgrimage, we arrange every part of the journey.', 'কাজ, ছুটি, পড়াশোনা বা হজ-উমরাহ—যে কারণেই যান, সফরের প্রতিটি অংশ আমরা সাজিয়ে দিই।'),
                'layout' => 'cards',
                'icon_style' => 'colorful',
                'columns' => '4',
                'items' => $this->coreServices(short: true),
            ], ['background' => 'white', 'decor' => 'shapes']],
            ['features', $this->credentials(), ['background' => 'navy-deep', 'spacing' => 'md', 'decor' => 'shapes']],
            ['image_text', [
                'eyebrow' => $this->t('Hajj & Umrah · Antarik Hajj Kafela', 'হজ ও উমরাহ · আন্তরিক হজ্জ কাফেলা'),
                'title' => $this->t('Hajj and Umrah, the way they deserve to be performed', 'যেভাবে হজ ও উমরাহ পালন করা উচিত, ঠিক সেভাবেই'),
                'body' => $this->t(
                    '<p>Antarik Hajj Kafela is our Hajj and Umrah division. Since 2016 it has guided pilgrims from Bangladesh with hotels near the Haram, Bangladeshi meals and experienced scholars beside them from the first workshop to the journey home.</p>',
                    '<p>আন্তরিক হজ্জ কাফেলা আমাদের হজ ও উমরাহ বিভাগ। ২০১৬ সাল থেকে হারামের কাছে হোটেল, বাংলাদেশি খাবার এবং প্রথম কর্মশালা থেকে দেশে ফেরা পর্যন্ত অভিজ্ঞ আলেমদের সান্নিধ্যে বাংলাদেশের হাজিদের সেবা দিয়ে আসছে।</p>'
                ),
                'bullets' => [
                    ['text' => $this->t('Government Hajj licence 602 and Umrah licence 411', 'সরকারি হজ লাইসেন্স ৬০২ ও উমরাহ লাইসেন্স ৪১১')],
                    ['text' => $this->t('Hajj 2028 registration is open', 'হজ ২০২৮-এর নিবন্ধন চলছে')],
                    ['text' => $this->t('Umrah groups every month of the year', 'বছরের প্রতি মাসে উমরাহ গ্রুপ')],
                    ['text' => $this->t('Scholar-led workshops before you travel', 'যাত্রার আগে আলেমদের কর্মশালা')],
                ],
                'image_position' => 'left',
                'badge' => $this->t('Since 2016', '২০১৬ থেকে'),
                'primary' => $this->link('/hajj', 'Hajj packages', 'হজ প্যাকেজ'),
                'secondary' => $this->link('/umrah', 'Umrah packages', 'উমরাহ প্যাকেজ'),
            ], ['background' => 'peach-soft', 'decor' => 'shapes']],
            ['lead_form', $this->airQuoteForm(home: true), ['background' => 'sky-soft', 'anchor' => 'air-quote', 'decor' => 'shapes']],
            ['features', array_replace($this->visaRegions(), [
                'cta' => $this->link('/visa', 'Visa services', 'ভিসা সেবা দেখুন'),
            ]), ['background' => 'mint-soft', 'decor' => 'shapes']],
            ['package_showcase', [
                'eyebrow' => $this->t('Popular tours', 'জনপ্রিয় ট্যুর'),
                'title' => $this->t('Where will you go next?', 'এবার কোথায় যাবেন?'),
                'intro' => $this->t('Ready-made holidays from Dhaka with flights, hotels and sightseeing. Every tour can be changed to suit you.', 'ফ্লাইট, হোটেল ও দর্শনীয় স্থানসহ ঢাকা থেকে তৈরি ছুটির প্যাকেজ। প্রতিটি ট্যুর আপনার মতো করে বদলানো যায়।'),
                'source' => 'service',
                'service' => (string) Service::where('slug', 'tours')->value('id'),
                'limit' => 6,
                'layout' => 'staggered',
                'cta' => $this->link('/tours', 'All tour packages', 'সব ট্যুর প্যাকেজ'),
            ], ['background' => 'navy-deep']],
            ['features', [
                'eyebrow' => $this->t('Why Antarik', 'কেন আন্তরিক'),
                'title' => $this->t('Travel with people who answer the phone', 'এমন মানুষের সাথে সফর করুন, যারা সবসময় পাশে থাকে'),
                'intro' => $this->t('Cheap fares and glossy brochures are easy to find. What travellers remember is the help they got when plans changed.', 'সস্তা ভাড়া আর চকচকে ব্রোশিওর সহজেই মেলে। ভ্রমণকারীরা মনে রাখেন পরিকল্পনা বদলালে কে পাশে ছিল।'),
                'layout' => 'numbered',
                'columns' => '2',
                'items' => [
                    ['icon' => 'medal', 'title' => $this->t('Licensed and accredited', 'লাইসেন্সপ্রাপ্ত ও স্বীকৃত'), 'text' => $this->t('IATA accredited, an ATAB member and licensed by Civil Aviation and the Ministry of Religious Affairs.', 'আইএটিএ স্বীকৃত, আটাবের সদস্য এবং সিভিল এভিয়েশন ও ধর্ম বিষয়ক মন্ত্রণালয়ের লাইসেন্সপ্রাপ্ত।'), 'link' => null],
                    ['icon' => 'headset', 'title' => $this->t('One team for the whole trip', 'পুরো সফরে একটি টিম'), 'text' => $this->t('The person who books your ticket also follows your visa and hotel, so nothing falls between desks.', 'যিনি আপনার টিকেট বুক করেন, তিনিই ভিসা ও হোটেলের খোঁজ রাখেন—কোনো কিছুই হারিয়ে যায় না।'), 'link' => null],
                    ['icon' => 'wallet', 'title' => $this->t('Clear prices, official accounts', 'স্পষ্ট মূল্য, অফিসিয়াল অ্যাকাউন্ট'), 'text' => $this->t('Written quotes with no hidden charges, and payments only to the company\'s official account.', 'লুকানো খরচ ছাড়া লিখিত কোটেশন, আর পেমেন্ট শুধু কোম্পানির অফিসিয়াল অ্যাকাউন্টে।'), 'link' => null],
                    ['icon' => 'shield', 'title' => $this->t('Help when plans change', 'পরিকল্পনা বদলালেও সহায়তা'), 'text' => $this->t('Missed connections, date changes or a lost bag: our Dhaka team is on WhatsApp while you travel.', 'কানেকটিং ফ্লাইট মিস, তারিখ পরিবর্তন বা ব্যাগ হারানো—সফরের সময় হোয়াটসঅ্যাপে আমাদের ঢাকা টিম পাশে থাকে।'), 'link' => null],
                ],
            ], ['background' => 'white']],
            ['process_steps', $this->howItWorks(), ['background' => 'lilac-soft', 'decor' => 'shapes']],
            ['testimonials', [
                'eyebrow' => $this->t('Traveller stories', 'ভ্রমণকারীদের কথা'),
                'title' => $this->t('In the words of our travellers', 'আমাদের ভ্রমণকারীদের মুখে'),
                'source' => 'featured',
                'limit' => 3,
                'cta' => $this->link('/reviews', 'Read all reviews', 'সব রিভিউ পড়ুন'),
            ], ['background' => 'white']],
            ['gallery_preview', [
                'eyebrow' => $this->t('Gallery', 'গ্যালারি'),
                'title' => $this->t('Moments from our journeys', 'আমাদের সফরের মুহূর্ত'),
                'limit' => '5',
                'cta' => $this->link('/gallery', 'Open the gallery', 'গ্যালারি দেখুন'),
            ], ['background' => 'navy-deep']],
            ['faq', $this->faqContent(), ['background' => 'sky-soft', 'anchor' => 'faq']],
            ['latest_posts', [
                'eyebrow' => $this->t('Guides & news', 'গাইড ও সংবাদ'),
                'title' => $this->t('Travel tips and updates', 'ভ্রমণ টিপস ও হালনাগাদ'),
                'limit' => 4,
                'layout' => 'magazine',
                'cta' => $this->link('/blog', 'All articles', 'সব লেখা'),
            ], ['background' => 'white']],
            $this->cta(),
        ];
    }

    /**
     * A text slide of the home hero.
     *
     * @return array<string, mixed>
     */
    private function slide(string $eyebrowEn, string $eyebrowBn, string $titleEn, string $titleBn, string $highlightEn, string $highlightBn, string $textEn, string $textBn, array $primary, ?array $secondary = null): array
    {
        return [
            'type' => 'text',
            'eyebrow' => $this->t($eyebrowEn, $eyebrowBn),
            'title' => $this->t($titleEn, $titleBn),
            'highlight' => $this->t($highlightEn, $highlightBn),
            'text' => $this->t($textEn, $textBn),
            'primary' => $primary,
            'secondary' => $secondary,
        ];
    }

    /**
     * @return list<array>
     */
    private function about(): array
    {
        return [
            // No banner: the page opens with the "Who we are" section, whose title is the page heading.
            ['about', [
                'eyebrow' => $this->t('Who we are', 'আমরা কারা'),
                'title' => $this->t('An international travel agency built on sincerity', 'আন্তরিকতার ওপর গড়া একটি আন্তর্জাতিক ট্রাভেল এজেন্সি'),
                'body' => $this->t(
                    '<p>Antarik Travel International began in Dhaka in 2016 as a Hajj and Umrah agency. The families we served soon asked us for more: tickets for their children studying abroad, visas for business trips, a holiday after the exams. Today we arrange air tickets, visas and tour packages around the world, and our Hajj and Umrah division, Antarik Hajj Kafela, still guides pilgrims every year.</p><p>Whatever the trip, we work the same way: honest advice, written prices, payments only to the company\'s official account, and a team that stays reachable until you are home.</p>',
                    '<p>আন্তরিক ট্রাভেল ইন্টারন্যাশনাল ২০১৬ সালে ঢাকায় একটি হজ ও উমরাহ এজেন্সি হিসেবে যাত্রা শুরু করে। যে পরিবারগুলোকে আমরা সেবা দিতাম, তারা শিগগিরই আরও চাইলেন: বিদেশে পড়তে যাওয়া সন্তানের টিকেট, ব্যবসায়িক সফরের ভিসা, পরীক্ষার পর একটি ছুটির ভ্রমণ। আজ আমরা সারা বিশ্বের এয়ার টিকেট, ভিসা ও ট্যুর প্যাকেজের ব্যবস্থা করি, আর আমাদের হজ ও উমরাহ বিভাগ আন্তরিক হজ্জ কাফেলা প্রতি বছর হাজিদের সেবা দিয়ে যাচ্ছে।</p><p>সফর যেমনই হোক, আমাদের কাজের ধরন একই: আন্তরিক পরামর্শ, লিখিত মূল্য, শুধু কোম্পানির অফিসিয়াল অ্যাকাউন্টে পেমেন্ট এবং দেশে ফেরা পর্যন্ত সবসময় পাশে থাকা একটি টিম।</p>'
                ),
                'years_value' => '2016',
                'years_label' => $this->t('Founded in Dhaka', 'ঢাকায় প্রতিষ্ঠিত'),
                'quote' => $this->t('We want every traveller to feel that someone at home is looking after their trip.', 'আমরা চাই প্রত্যেক ভ্রমণকারী অনুভব করুন, দেশে কেউ তার সফরের খেয়াল রাখছে।'),
                'quote_author' => $this->t('Antarik Travel International', 'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল'),
            ], ['background' => 'white', 'decor' => 'shapes']],
            ['features', [
                'eyebrow' => $this->t('Our purpose', 'আমাদের লক্ষ্য'),
                'title' => $this->t('Vision & mission', 'ভিশন ও মিশন'),
                'layout' => 'tiles',
                'icon_style' => 'colorful',
                'columns' => '2',
                'items' => [
                    ['icon' => 'star', 'title' => $this->t('Vision', 'ভিশন'), 'text' => $this->t('To be the travel agency Bangladeshi families trust first, for the pilgrimage and for every journey after it.', 'বাংলাদেশি পরিবারগুলোর প্রথম ভরসার ট্রাভেল এজেন্সি হওয়া—হজ-উমরাহ এবং তার পরের প্রতিটি সফরে।'), 'link' => null],
                    ['icon' => 'heart', 'title' => $this->t('Mission', 'মিশন'), 'text' => $this->t('To make international travel simple, safe and fairly priced, with clear advice and real support before, during and after every trip.', 'স্পষ্ট পরামর্শ এবং প্রতিটি সফরের আগে, চলাকালীন ও পরে সত্যিকারের সহায়তার মাধ্যমে আন্তর্জাতিক ভ্রমণকে সহজ, নিরাপদ ও ন্যায্যমূল্যের করা।'), 'link' => null],
                ],
            ], ['background' => 'sky-soft', 'spacing' => 'md', 'decor' => 'shapes']],
            ['features', [
                'eyebrow' => $this->t('What we do', 'আমরা যা করি'),
                'title' => $this->t('Our services', 'আমাদের সেবাসমূহ'),
                'layout' => 'cards',
                'icon_style' => 'colorful',
                'columns' => '4',
                'items' => $this->coreServices(short: true),
            ], ['background' => 'mint-soft', 'decor' => 'shapes']],
            ['image_text', [
                'eyebrow' => $this->t('Our Hajj & Umrah division', 'আমাদের হজ ও উমরাহ বিভাগ'),
                'title' => $this->t('Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলা'),
                'body' => $this->t(
                    '<p>The pilgrimage is where we started, and it is still at the heart of our work. Antarik Hajj Kafela holds government Hajj licence 602 and Umrah licence 411, and travels with a team of experienced scholars who manage, supervise and guide every group.</p><p>Language barriers, lack of guidance and unclear dealings keep many pilgrims from the Hajj they hoped for. Our division was built to prevent exactly that.</p>',
                    '<p>হজ-উমরাহ দিয়েই আমাদের শুরু, আর এটিই এখনো আমাদের কাজের কেন্দ্রে। আন্তরিক হজ্জ কাফেলার রয়েছে সরকারি হজ লাইসেন্স ৬০২ ও উমরাহ লাইসেন্স ৪১১, এবং প্রতিটি দলের ব্যবস্থাপনা, তত্ত্বাবধান ও দিকনির্দেশনায় থাকেন অভিজ্ঞ আলেমদের একটি টিম।</p><p>ভাষাগত বাধা, দিকনির্দেশনার অভাব ও অস্বচ্ছ লেনদেনের কারণে অনেকেই কাঙ্ক্ষিত হজ করতে পারেন না। ঠিক এগুলো দূর করতেই আমাদের এই বিভাগ।</p>'
                ),
                'bullets' => [
                    ['text' => $this->t('Qualified, skilled and well-mannered management', 'যোগ্য, দক্ষ ও সদাচারী ব্যবস্থাপনা')],
                    ['text' => $this->t('Scholars with practical knowledge of Hajj', 'হজের ব্যবহারিক জ্ঞানসম্পন্ন আলেম')],
                    ['text' => $this->t('Itineraries that follow the rulings of Hajj', 'হজের বিধান মেনে সাজানো সফরসূচি')],
                    ['text' => $this->t('Transparent and fair financial dealings', 'স্বচ্ছ ও ন্যায্য আর্থিক লেনদেন')],
                ],
                'image_position' => 'left',
                'primary' => $this->link('/hajj', 'Hajj packages', 'হজ প্যাকেজ'),
                'secondary' => $this->link('/umrah', 'Umrah packages', 'উমরাহ প্যাকেজ'),
            ], ['background' => 'peach-soft', 'decor' => 'shapes']],
            ['features', $this->credentials(), ['background' => 'navy-deep', 'spacing' => 'md', 'decor' => 'shapes']],
            ['team', [
                'eyebrow' => $this->t('Our people', 'আমাদের টিম'),
                'title' => $this->t('The people behind your journey', 'আপনার সফরের পেছনের মানুষেরা'),
                'members' => [
                    ['name' => $this->t('Mesbah Uddin Saeed', 'মেসবাহ উদ্দিন সাঈদ'), 'role' => $this->t('Chairman', 'চেয়ারম্যান')],
                    ['name' => $this->t('Hafiz Rashedul Islam', 'হাফেজ রাশেদুল ইসলাম'), 'role' => $this->t('Managing Director', 'ব্যবস্থাপনা পরিচালক')],
                    ['name' => $this->t('A.B.M. Azizur Rahman Belal', 'এ.বি.এম. আজিজুর রহমান বেলাল'), 'role' => $this->t('Hajj Director', 'হজ পরিচালক')],
                    ['name' => $this->t('Maulana Hossain Ahmad', 'মাওলানা হোসাইন আহমাদ'), 'role' => $this->t('Official Representative', 'অফিসিয়াল প্রতিনিধি')],
                    ['name' => $this->t('Maulana Saiful Islam', 'মাওলানা সাইফুল ইসলাম'), 'role' => $this->t('Official Representative', 'অফিসিয়াল প্রতিনিধি')],
                ],
            ], ['background' => 'lilac-soft', 'decor' => 'shapes']],
            $this->cta(),
        ];
    }

    /**
     * @return list<array>
     */
    private function services(): array
    {
        return [
            $this->banner(
                $this->t('Our services', 'আমাদের সেবাসমূহ'),
                $this->t('Air tickets, visa processing, tour packages, Hajj and Umrah, arranged by one licensed team in Dhaka.', 'এয়ার টিকেট, ভিসা প্রসেসিং, ট্যুর প্যাকেজ, হজ ও উমরাহ—ঢাকার একটি লাইসেন্সপ্রাপ্ত টিমের মাধ্যমে।'),
                $this->t('What we do', 'আমরা যা করি'),
            ),
            ['features', [
                'eyebrow' => $this->t('Trusted international travel services', 'বিশ্বস্ত আন্তর্জাতিক ট্রাভেল সেবা'),
                'title' => $this->t('Everything your trip needs', 'আপনার সফরের সব প্রয়োজন'),
                'layout' => 'cards',
                'icon_style' => 'colorful',
                'columns' => '2',
                'items' => $this->coreServices(short: false),
            ], ['background' => 'white', 'decor' => 'shapes']],
            ['features', [
                'eyebrow' => $this->t('Along the way', 'সফরের পথে'),
                'title' => $this->t('Included with every booking', 'প্রতিটি বুকিংয়ের সাথে'),
                'layout' => 'tiles',
                'icon_style' => 'colorful',
                'columns' => '4',
                'items' => [
                    ['icon' => 'hotel', 'title' => $this->t('Hotel bookings', 'হোটেল বুকিং'), 'text' => $this->t('Hotels in every budget, booked with your ticket or on their own.', 'সব বাজেটের হোটেল—টিকেটের সাথে বা আলাদাভাবে।'), 'link' => null],
                    ['icon' => 'bus', 'title' => $this->t('Airport transfers', 'এয়ারপোর্ট ট্রান্সফার'), 'text' => $this->t('A driver waiting when you land, in every tour city.', 'প্রতিটি ট্যুর শহরে নামার সাথে সাথে অপেক্ষমাণ গাড়ি।'), 'link' => null],
                    ['icon' => 'document', 'title' => $this->t('Travel documents', 'ভ্রমণের কাগজপত্র'), 'text' => $this->t('Itineraries, hotel letters and insurance for your visa file.', 'ভিসা ফাইলের জন্য ভ্রমণসূচি, হোটেল লেটার ও বিমা।'), 'link' => null],
                    ['icon' => 'headset', 'title' => $this->t('Support on the road', 'সফরে সহায়তা'), 'text' => $this->t('Our Dhaka team on WhatsApp until you are back home.', 'দেশে ফেরা পর্যন্ত হোয়াটসঅ্যাপে আমাদের ঢাকা টিম।'), 'link' => null],
                ],
            ], ['background' => 'sky-soft', 'decor' => 'shapes']],
            ['process_steps', $this->howItWorks(), ['background' => 'lilac-soft', 'decor' => 'shapes']],
            $this->cta(),
        ];
    }

    /**
     * @return list<array>
     */
    private function contact(): array
    {
        return [
            // No banner: the page opens with the concierge panel (ways to reach us beside the form).
            ['contact_form', [
                'eyebrow' => $this->t('Get in touch', 'যোগাযোগ'),
                'title' => $this->t('Talk to our team', 'আমাদের টিমের সাথে কথা বলুন'),
                'intro' => $this->t('For air tickets, visas, tours, Hajj, Umrah or any question about your trip.', 'এয়ার টিকেট, ভিসা, ট্যুর, হজ, উমরাহ বা আপনার সফর সম্পর্কিত যেকোনো প্রশ্নে।'),
                'layout' => 'concierge',
            ], ['background' => 'sky-soft', 'decor' => 'shapes']],
            ['faq', array_replace($this->faqContent(), [
                'eyebrow' => $this->t('Quick answers', 'সংক্ষিপ্ত উত্তর'),
                'intro' => $this->t('Many questions are answered here before you call.', 'কল করার আগেই অনেক প্রশ্নের উত্তর এখানে পেয়ে যাবেন।'),
                'layout' => 'stacked',
                'cta' => null,
            ]), ['background' => 'lilac-soft', 'decor' => 'shapes']],
        ];
    }

    /**
     * The Air Tickets page: a fare quote form that goes to the admin's inquiries.
     *
     * @return array<string, mixed>
     */
    private function airTickets(): array
    {
        return [
            'type' => Page::TYPE_CUSTOM,
            'system_key' => null,
            'slug' => 'air-tickets',
            'title' => $this->t('Air tickets', 'এয়ার টিকেট'),
            'sections' => [
                ['page_banner', [
                    'eyebrow' => $this->t('Air tickets', 'এয়ার টিকেট'),
                    'title' => $this->t('Air tickets to anywhere in the world', 'বিশ্বের যেকোনো গন্তব্যের এয়ার টিকেট'),
                    'highlight' => $this->t('at fares we check for you.', 'যাচাই করা সেরা ভাড়ায়।'),
                    'intro' => $this->t('Tell us where and when you want to fly. Our ticketing team compares the airlines and sends you the best options.', 'কোথায় ও কবে যেতে চান জানান। আমাদের টিকেটিং টিম এয়ারলাইনগুলো তুলনা করে সেরা অপশনগুলো পাঠাবে।'),
                ], []],
                ['lead_form', $this->airQuoteForm(home: false), ['background' => 'sky-soft', 'anchor' => 'quote', 'decor' => 'shapes']],
                ['features', [
                    'eyebrow' => $this->t('Why book with us', 'কেন আমাদের কাছে বুক করবেন'),
                    'title' => $this->t('More than the cheapest fare', 'শুধু সবচেয়ে কম ভাড়া নয়'),
                    'layout' => 'cards',
                    'icon_style' => 'colorful',
                    'columns' => '4',
                    'items' => [
                        ['icon' => 'medal', 'title' => $this->t('IATA accredited', 'আইএটিএ স্বীকৃত'), 'text' => $this->t('We issue tickets directly on every major airline, so your booking is confirmed on the spot.', 'সব বড় এয়ারলাইনের টিকেট আমরা সরাসরি ইস্যু করি, তাই বুকিং সাথে সাথেই নিশ্চিত।'), 'link' => null],
                        ['icon' => 'plane', 'title' => $this->t('Every route', 'সব রুট'), 'text' => $this->t('Middle East, Asia, Europe, North America and domestic flights, one-way or return.', 'মধ্যপ্রাচ্য, এশিয়া, ইউরোপ, উত্তর আমেরিকা ও অভ্যন্তরীণ ফ্লাইট—ওয়ান-ওয়ে বা রিটার্ন।'), 'link' => null],
                        ['icon' => 'users', 'title' => $this->t('Group and student fares', 'গ্রুপ ও স্টুডেন্ট ফেয়ার'), 'text' => $this->t('Special fares for groups, students and workers, with extra baggage where airlines allow it.', 'গ্রুপ, শিক্ষার্থী ও কর্মীদের জন্য বিশেষ ভাড়া, এয়ারলাইন অনুমতি দিলে অতিরিক্ত ব্যাগেজসহ।'), 'link' => null],
                        ['icon' => 'calendar', 'title' => $this->t('Changes handled', 'পরিবর্তনেও পাশে'), 'text' => $this->t('Date changes, name corrections and refunds handled by the same team that sold you the ticket.', 'তারিখ পরিবর্তন, নাম সংশোধন ও রিফান্ড—যে টিম টিকেট দিয়েছে, তারাই দেখে।'), 'link' => null],
                    ],
                ], ['background' => 'white', 'decor' => 'shapes']],
                ['faq', [
                    'eyebrow' => $this->t('Air ticket questions', 'এয়ার টিকেট প্রশ্নোত্তর'),
                    'title' => $this->t('Before you book', 'বুক করার আগে'),
                    'layout' => 'split',
                    'items' => $this->qa([
                        ['How quickly will I get a quote?', 'কত দ্রুত কোটেশন পাব?', '<p>During office hours we usually reply the same day by phone or WhatsApp. For urgent travel, call us directly.</p>', '<p>অফিস সময়ে সাধারণত একই দিনে ফোন বা হোয়াটসঅ্যাপে উত্তর দিই। জরুরি সফরের জন্য সরাসরি কল করুন।</p>'],
                        ['Is the quoted fare guaranteed?', 'কোটেশনের ভাড়া কি নিশ্চিত?', '<p>Airline fares change with seat availability. A fare is guaranteed once the ticket is issued after payment; until then we hold seats where the airline allows.</p>', '<p>আসন প্রাপ্যতার সাথে এয়ারলাইনের ভাড়া বদলায়। পেমেন্টের পর টিকেট ইস্যু হলে ভাড়া নিশ্চিত হয়; তার আগে এয়ারলাইন অনুমতি দিলে আসন হোল্ড রাখি।</p>'],
                        ['Can I change my date or get a refund?', 'তারিখ পরিবর্তন বা রিফান্ড করা যাবে?', '<p>It depends on the fare rules of your ticket, which we explain before you pay. We handle the change or refund with the airline for you.</p>', '<p>এটি আপনার টিকেটের ফেয়ার রুলসের ওপর নির্ভর করে, যা পেমেন্টের আগেই আমরা বুঝিয়ে দিই। এয়ারলাইনের সাথে পরিবর্তন বা রিফান্ডের কাজ আমরাই করি।</p>'],
                        ['What do I need to book?', 'বুক করতে কী লাগবে?', '<p>The traveller\'s name exactly as in the passport, the passport number and expiry date, and a contact number.</p>', '<p>পাসপোর্ট অনুযায়ী যাত্রীর হুবহু নাম, পাসপোর্ট নম্বর ও মেয়াদ এবং একটি যোগাযোগ নম্বর।</p>'],
                    ]),
                    'cta' => $this->link('/contact', 'Contact us', 'যোগাযোগ করুন'),
                ], ['background' => 'mint-soft', 'decor' => 'shapes']],
                $this->cta(),
            ],
        ];
    }

    /**
     * The Visa page: visa types, countries by region, documents and an enquiry form.
     *
     * @return array<string, mixed>
     */
    private function visa(): array
    {
        return [
            'type' => Page::TYPE_CUSTOM,
            'system_key' => null,
            'slug' => 'visa',
            'title' => $this->t('Visa processing', 'ভিসা প্রসেসিং'),
            'sections' => [
                ['page_banner', [
                    'eyebrow' => $this->t('Visa processing', 'ভিসা প্রসেসিং'),
                    'title' => $this->t('Visa processing, done properly', 'সঠিকভাবে ভিসা প্রসেসিং'),
                    'highlight' => $this->t('from the first document to the stamp.', 'প্রথম কাগজ থেকে স্ট্যাম্প পর্যন্ত।'),
                    'intro' => $this->t('Tourist, business, student and work visas for the Middle East, Asia, Europe, the UK, North America and Oceania.', 'মধ্যপ্রাচ্য, এশিয়া, ইউরোপ, যুক্তরাজ্য, উত্তর আমেরিকা ও ওশেনিয়ার ট্যুরিস্ট, বিজনেস, স্টুডেন্ট ও ওয়ার্ক ভিসা।'),
                ], []],
                ['features', [
                    'eyebrow' => $this->t('Visa types', 'ভিসার ধরন'),
                    'title' => $this->t('Which visa do you need?', 'আপনার কোন ভিসা দরকার?'),
                    'layout' => 'cards',
                    'icon_style' => 'colorful',
                    'columns' => '4',
                    'items' => [
                        ['icon' => 'globe', 'title' => $this->t('Tourist visa', 'ট্যুরিস্ট ভিসা'), 'text' => $this->t('Holidays and family visits, with the itinerary, hotel and ticket bookings the embassy asks for.', 'ছুটি ও পারিবারিক ভ্রমণ—দূতাবাসের চাওয়া ভ্রমণসূচি, হোটেল ও টিকেট বুকিংসহ।'), 'link' => null],
                        ['icon' => 'building', 'title' => $this->t('Business visa', 'বিজনেস ভিসা'), 'text' => $this->t('Meetings, trade fairs and conferences, with the invitation letter and company papers in order.', 'মিটিং, বাণিজ্য মেলা ও সম্মেলন—আমন্ত্রণপত্র ও কোম্পানির কাগজপত্র গুছিয়ে।'), 'link' => null],
                        ['icon' => 'book', 'title' => $this->t('Student visa', 'স্টুডেন্ট ভিসা'), 'text' => $this->t('Offer letters, financial documents and interview preparation for studying abroad.', 'বিদেশে পড়াশোনার জন্য অফার লেটার, আর্থিক কাগজপত্র ও ইন্টারভিউ প্রস্তুতি।'), 'link' => null],
                        ['icon' => 'passport', 'title' => $this->t('Work visa', 'ওয়ার্ক ভিসা'), 'text' => $this->t('Employment visas on a genuine job offer, with attestation and medical arranged.', 'প্রকৃত চাকরির অফারে কর্মসংস্থান ভিসা—সত্যায়ন ও মেডিকেলের ব্যবস্থাসহ।'), 'link' => null],
                    ],
                ], ['background' => 'white', 'decor' => 'shapes']],
                ['features', $this->visaRegions(), ['background' => 'mint-soft', 'decor' => 'shapes']],
                ['image_text', [
                    'eyebrow' => $this->t('Documents', 'কাগজপত্র'),
                    'title' => $this->t('What most visa files need', 'বেশিরভাগ ভিসা ফাইলে যা লাগে'),
                    'body' => $this->t(
                        '<p>Every embassy has its own list, and we give you the exact one for your country and visa type. Most files start with the documents below, so it helps to have them ready.</p>',
                        '<p>প্রতিটি দূতাবাসের নিজস্ব তালিকা আছে, আর আপনার দেশ ও ভিসার ধরন অনুযায়ী সঠিক তালিকাটি আমরা দিই। বেশিরভাগ ফাইল নিচের কাগজগুলো দিয়ে শুরু হয়, তাই আগে থেকে প্রস্তুত রাখা ভালো।</p>'
                    ),
                    'bullets' => [
                        ['text' => $this->t('Passport valid for at least 6 months, with the old passports', 'কমপক্ষে ৬ মাস মেয়াদি পাসপোর্ট, পুরোনো পাসপোর্টসহ')],
                        ['text' => $this->t('Recent photos in the embassy\'s size', 'দূতাবাসের নির্ধারিত মাপের সাম্প্রতিক ছবি')],
                        ['text' => $this->t('Bank statement and solvency certificate', 'ব্যাংক স্টেটমেন্ট ও সলভেন্সি সার্টিফিকেট')],
                        ['text' => $this->t('Job letter or NOC, or trade licence for business owners', 'চাকরির প্রত্যয়নপত্র বা এনওসি, ব্যবসায়ীদের ট্রেড লাইসেন্স')],
                        ['text' => $this->t('Tax return (TIN certificate) where asked', 'চাওয়া হলে আয়কর রিটার্ন (টিআইএন সার্টিফিকেট)')],
                        ['text' => $this->t('Invitation, offer or admission letter for business, work and study', 'বিজনেস, কাজ ও পড়াশোনার জন্য আমন্ত্রণ, অফার বা ভর্তির চিঠি')],
                    ],
                    'image_position' => 'right',
                ], ['background' => 'peach-soft', 'decor' => 'shapes']],
                ['process_steps', [
                    'eyebrow' => $this->t('How it works', 'যেভাবে কাজ করি'),
                    'title' => $this->t('Your visa in four steps', 'চার ধাপে আপনার ভিসা'),
                    'steps' => [
                        ['icon' => 'headset', 'title' => $this->t('Free consultation', 'ফ্রি পরামর্শ'), 'text' => $this->t('We look at your travel plan and tell you honestly which visa fits and what it needs.', 'আপনার ভ্রমণ পরিকল্পনা দেখে আন্তরিকভাবে জানাই কোন ভিসা উপযুক্ত ও কী কী লাগবে।')],
                        ['icon' => 'document', 'title' => $this->t('File preparation', 'ফাইল প্রস্তুত'), 'text' => $this->t('We check every document, fill in the forms and prepare the supporting letters.', 'প্রতিটি কাগজ যাচাই, ফরম পূরণ ও সহায়ক চিঠি প্রস্তুত করি।')],
                        ['icon' => 'calendar', 'title' => $this->t('Appointment & submission', 'অ্যাপয়েন্টমেন্ট ও জমা'), 'text' => $this->t('We book the embassy or visa centre appointment and brief you before you go.', 'দূতাবাস বা ভিসা সেন্টারের অ্যাপয়েন্টমেন্ট নিই এবং যাওয়ার আগে আপনাকে ব্রিফ করি।')],
                        ['icon' => 'check-circle', 'title' => $this->t('Follow-up', 'ফলোআপ'), 'text' => $this->t('We track the application and help you book flights once the visa is issued.', 'আবেদনের অগ্রগতি দেখি এবং ভিসা হলে ফ্লাইট বুকিংয়ে সহায়তা করি।')],
                    ],
                ], ['background' => 'lilac-soft', 'decor' => 'shapes']],
                ['lead_form', [
                    'eyebrow' => $this->t('Visa enquiry', 'ভিসা জিজ্ঞাসা'),
                    'title' => $this->t('Ask about your visa', 'আপনার ভিসা সম্পর্কে জানুন'),
                    'intro' => $this->t('Tell us the country and the purpose of your trip. A visa consultant will call you back.', 'দেশ ও সফরের উদ্দেশ্য জানান। একজন ভিসা পরামর্শক আপনাকে কল করবেন।'),
                    'fields' => [
                        ['field' => 'visa_country', 'required' => true],
                        ['field' => 'visa_type', 'required' => true],
                        ['field' => 'departure_date', 'label' => $this->t('Planned travel date', 'পরিকল্পিত ভ্রমণের তারিখ'), 'required' => false],
                        ['field' => 'name', 'required' => true],
                        ['field' => 'phone', 'required' => true],
                        ['field' => 'email', 'required' => false],
                        ['field' => 'message', 'label' => $this->t('Anything we should know?', 'আর কিছু জানাতে চান?'), 'required' => false],
                    ],
                    'country_options' => $this->t(implode("\n", array_column($this->visaCountries(), 0)), implode("\n", array_column($this->visaCountries(), 1))),
                    'submit_label' => $this->t('Send my enquiry', 'জিজ্ঞাসা পাঠান'),
                    'success' => $this->t('Thank you. A visa consultant will call you shortly.', 'ধন্যবাদ। একজন ভিসা পরামর্শক শিগগিরই আপনাকে কল করবেন।'),
                    'bullets' => [
                        ['text' => $this->t('Free first consultation', 'প্রথম পরামর্শ বিনামূল্যে')],
                        ['text' => $this->t('A document list for your exact visa', 'আপনার ভিসার জন্য নির্দিষ্ট কাগজপত্রের তালিকা')],
                        ['text' => $this->t('Honest advice on your chances', 'সম্ভাবনা নিয়ে আন্তরিক পরামর্শ')],
                    ],
                ], ['background' => 'sky-soft', 'anchor' => 'enquiry', 'decor' => 'shapes']],
                ['faq', [
                    'eyebrow' => $this->t('Visa questions', 'ভিসা প্রশ্নোত্তর'),
                    'title' => $this->t('Good to know', 'জেনে রাখুন'),
                    'layout' => 'split',
                    'items' => $this->qa([
                        ['Can you guarantee my visa?', 'আপনারা কি ভিসার নিশ্চয়তা দেন?', '<p>No agency can: only the embassy decides. What we guarantee is a complete, correct file and honest advice before you apply.</p>', '<p>কোনো এজেন্সিই পারে না—সিদ্ধান্ত শুধু দূতাবাসের। আমরা নিশ্চয়তা দিই সম্পূর্ণ ও নির্ভুল ফাইল এবং আবেদনের আগে আন্তরিক পরামর্শের।</p>'],
                        ['How long does a visa take?', 'ভিসা হতে কত সময় লাগে?', '<p>From a few days for many e-visas to several weeks for Schengen, UK, US or student visas. We tell you the current time when you enquire, so plan early.</p>', '<p>অনেক ই-ভিসার ক্ষেত্রে কয়েক দিন থেকে শেনজেন, যুক্তরাজ্য, যুক্তরাষ্ট্র বা স্টুডেন্ট ভিসায় কয়েক সপ্তাহ। জিজ্ঞাসা করলে বর্তমান সময় জানিয়ে দিই, তাই আগে থেকে পরিকল্পনা করুন।</p>'],
                        ['Is the embassy fee included in your service charge?', 'দূতাবাসের ফি কি সার্ভিস চার্জে অন্তর্ভুক্ত?', '<p>No. Embassy and visa centre fees are paid at cost, and our service charge is quoted separately in writing.</p>', '<p>না। দূতাবাস ও ভিসা সেন্টারের ফি প্রকৃত খরচে পরিশোধ করতে হয়; আমাদের সার্ভিস চার্জ আলাদাভাবে লিখিত জানানো হয়।</p>'],
                        ['Do you handle Umrah visas too?', 'উমরাহ ভিসাও কি করেন?', '<p>Yes. Umrah visas are arranged by our Hajj and Umrah division with every Umrah package, or on their own.</p>', '<p>হ্যাঁ। আমাদের হজ ও উমরাহ বিভাগ প্রতিটি উমরাহ প্যাকেজের সাথে, অথবা আলাদাভাবে উমরাহ ভিসার ব্যবস্থা করে।</p>'],
                    ]),
                    'cta' => $this->link('/contact', 'Contact us', 'যোগাযোগ করুন'),
                ], ['background' => 'white', 'decor' => 'shapes']],
                $this->cta(),
            ],
        ];
    }

    /**
     * The fare quote form (home page and the Air Tickets page).
     *
     * @return array<string, mixed>
     */
    private function airQuoteForm(bool $home): array
    {
        return [
            'eyebrow' => $this->t('Air ticket quote', 'এয়ার টিকেট কোটেশন'),
            'title' => $home ? $this->t('Where are you flying?', 'কোথায় যাচ্ছেন?') : $this->t('Request a fare quote', 'ভাড়ার কোটেশন নিন'),
            'intro' => $this->t('Share your route and dates. We compare the airlines and send you the best fares by phone or WhatsApp.', 'রুট ও তারিখ জানান। আমরা এয়ারলাইনগুলো তুলনা করে ফোন বা হোয়াটসঅ্যাপে সেরা ভাড়া জানাব।'),
            'fields' => [
                ['field' => 'trip_type', 'required' => true],
                ['field' => 'cabin_class', 'required' => false],
                ['field' => 'from_city', 'required' => true],
                ['field' => 'to_city', 'required' => true],
                ['field' => 'departure_date', 'required' => true],
                ['field' => 'return_date', 'required' => false],
                ['field' => 'adults', 'required' => true],
                ['field' => 'children', 'required' => false],
                ['field' => 'name', 'required' => true],
                ['field' => 'phone', 'required' => true],
            ],
            'submit_label' => $this->t('Get my quote', 'কোটেশন নিন'),
            'success' => $this->t('Thank you. Our ticketing team will send you the best fares shortly.', 'ধন্যবাদ। আমাদের টিকেটিং টিম শিগগিরই আপনাকে সেরা ভাড়া জানাবে।'),
            'bullets' => [
                ['text' => $this->t('IATA accredited ticketing', 'আইএটিএ স্বীকৃত টিকেটিং')],
                ['text' => $this->t('Fares from every major airline compared', 'সব বড় এয়ারলাইনের ভাড়া তুলনা')],
                ['text' => $this->t('Group, student and Umrah fares', 'গ্রুপ, স্টুডেন্ট ও উমরাহ ফেয়ার')],
                ['text' => $this->t('No booking fee for a quote', 'কোটেশনের জন্য কোনো ফি নেই')],
            ],
        ];
    }

    /**
     * The four core services, linking to their pages.
     *
     * @return list<array<string, mixed>>
     */
    private function coreServices(bool $short): array
    {
        $items = [
            ['kaaba', '/hajj', 'Hajj & Umrah', 'হজ ও উমরাহ',
                'Scholar-guided Hajj and Umrah from Antarik Hajj Kafela, our licensed division.',
                'আমাদের লাইসেন্সপ্রাপ্ত বিভাগ আন্তরিক হজ্জ কাফেলার মাধ্যমে আলেমদের তত্ত্বাবধানে হজ ও উমরাহ।',
                'Hajj packages for 2028 and Umrah every month, with hotels near the Haram, Bangladeshi meals, visas, flights and experienced scholars beside you. Hajj licence 602, Umrah licence 411.',
                'হজ ২০২৮-এর প্যাকেজ ও প্রতি মাসে উমরাহ—হারামের কাছে হোটেল, বাংলাদেশি খাবার, ভিসা, ফ্লাইট ও অভিজ্ঞ আলেমদের সান্নিধ্যে। হজ লাইসেন্স ৬০২, উমরাহ লাইসেন্স ৪১১।',
                'Hajj & Umrah packages', 'হজ ও উমরাহ প্যাকেজ'],
            ['plane', '/air-tickets', 'Air tickets', 'এয়ার টিকেট',
                'Domestic and international fares on every major airline, compared for you.',
                'সব বড় এয়ারলাইনের অভ্যন্তরীণ ও আন্তর্জাতিক ভাড়া, তুলনা করে।',
                'One-way, return and multi-city tickets worldwide from an IATA accredited agent, with group, student and worker fares, date changes and refunds handled for you.',
                'আইএটিএ স্বীকৃত এজেন্টের কাছ থেকে বিশ্বজুড়ে ওয়ান-ওয়ে, রিটার্ন ও মাল্টি-সিটি টিকেট—গ্রুপ, স্টুডেন্ট ও কর্মী ফেয়ার, তারিখ পরিবর্তন ও রিফান্ডসহ।',
                'Request a fare quote', 'ভাড়ার কোটেশন নিন'],
            ['passport', '/visa', 'Visa processing', 'ভিসা প্রসেসিং',
                'Tourist, business, student and work visas with a complete, checked file.',
                'যাচাই করা সম্পূর্ণ ফাইলসহ ট্যুরিস্ট, বিজনেস, স্টুডেন্ট ও ওয়ার্ক ভিসা।',
                'Visa files for the Middle East, Asia, Europe and the UK, North America and Oceania: document checks, forms, appointments and follow-up, with honest advice before you apply.',
                'মধ্যপ্রাচ্য, এশিয়া, ইউরোপ ও যুক্তরাজ্য, উত্তর আমেরিকা ও ওশেনিয়ার ভিসা ফাইল: কাগজপত্র যাচাই, ফরম, অ্যাপয়েন্টমেন্ট ও ফলোআপ—আবেদনের আগে আন্তরিক পরামর্শসহ।',
                'Visa services', 'ভিসা সেবা'],
            ['globe', '/tours', 'Tour packages', 'ট্যুর প্যাকেজ',
                'Holidays from Dhaka with flights, hotels and sightseeing in one price.',
                'ফ্লাইট, হোটেল ও দর্শনীয় স্থানসহ ঢাকা থেকে এক মূল্যে ছুটির সফর।',
                'Ready-made and tailor-made tours to Asia\'s favourites, Europe, Japan and the great Islamic cities, for families, couples and company groups.',
                'এশিয়ার জনপ্রিয় গন্তব্য, ইউরোপ, জাপান ও ইসলামি ঐতিহ্যের শহরে তৈরি ও কাস্টম ট্যুর—পরিবার, দম্পতি ও অফিস গ্রুপের জন্য।',
                'Explore tours', 'ট্যুর দেখুন'],
        ];

        return array_map(fn ($i) => [
            'icon' => $i[0],
            'title' => $this->t($i[2], $i[3]),
            'text' => $short ? $this->t($i[4], $i[5]) : $this->t($i[6], $i[7]),
            'link' => $this->link($i[1], $i[8], $i[9]),
        ], $items);
    }

    /**
     * The agency's licences and memberships.
     *
     * @return array<string, mixed>
     */
    /**
     * The licences and memberships, in order. Shared with the migration that adds the two newer ones.
     *
     * @param  callable(string, string): mixed  $t
     * @return list<array<string, mixed>>
     */
    public static function credentialItems(callable $t): array
    {
        return [
            ['icon' => 'kaaba', 'title' => $t('Hajj 602 · Umrah 411', 'হজ ৬০২ · উমরাহ ৪১১'), 'text' => $t('Government Hajj and Umrah licences, Ministry of Religious Affairs.', 'ধর্ম বিষয়ক মন্ত্রণালয়ের সরকারি হজ ও উমরাহ লাইসেন্স।'), 'link' => null],
            ['icon' => 'plane', 'title' => $t('IATA accredited', 'আইএটিএ স্বীকৃত'), 'text' => $t('Accredited by the International Air Transport Association to issue tickets.', 'টিকেট ইস্যুর জন্য ইন্টারন্যাশনাল এয়ার ট্রান্সপোর্ট অ্যাসোসিয়েশনের স্বীকৃতি।'), 'link' => null],
            ['icon' => 'users', 'title' => $t('ATAB member', 'আটাব সদস্য'), 'text' => $t('Member of the Association of Travel Agents of Bangladesh.', 'অ্যাসোসিয়েশন অব ট্রাভেল এজেন্টস অব বাংলাদেশের সদস্য।'), 'link' => null],
            ['icon' => 'mosque', 'title' => $t('HAAB member', 'হাব সদস্য'), 'text' => $t('Member of the Hajj Agencies Association of Bangladesh.', 'হজ এজেন্সিজ অ্যাসোসিয়েশন অব বাংলাদেশের সদস্য।'), 'link' => null],
            ['icon' => 'plane', 'title' => $t('Biman Bangladesh Airlines', 'বিমান বাংলাদেশ এয়ারলাইন্স'), 'text' => $t('Tickets and Hajj flights on the national airline of Bangladesh.', 'বাংলাদেশের জাতীয় এয়ারলাইনে টিকেট ও হজ ফ্লাইট।'), 'link' => null],
            ['icon' => 'shield', 'title' => $t('Civil Aviation licence', 'সিভিল এভিয়েশন লাইসেন্স'), 'text' => $t('Licensed travel agency under the Ministry of Civil Aviation and Tourism.', 'বেসামরিক বিমান পরিবহন ও পর্যটন মন্ত্রণালয়ের লাইসেন্সপ্রাপ্ত ট্রাভেল এজেন্সি।'), 'link' => null],
        ];
    }

    private function credentials(): array
    {
        return [
            'eyebrow' => $this->t('Licensed & accredited', 'লাইসেন্সপ্রাপ্ত ও স্বীকৃত'),
            'title' => $this->t('A travel agency you can check', 'যাচাই করে নিতে পারেন এমন ট্রাভেল এজেন্সি'),
            'intro' => $this->t('Government licences and industry memberships you can verify before you travel with us.', 'আমাদের সাথে সফরের আগেই যাচাই করে নিতে পারেন এমন সরকারি লাইসেন্স ও সদস্যপদ।'),
            'layout' => 'badges',
            'columns' => '3',
            'icon_style' => 'colorful',
            // Logos (usage "logo") are filled by HeroImagesSeeder from database/seeders/images/credentials.
            'items' => self::credentialItems(fn (string $en, string $bn) => $this->t($en, $bn)),
        ];
    }

    /**
     * Visa countries by region (the Visa page and the home page).
     *
     * @return array<string, mixed>
     */
    private function visaRegions(): array
    {
        $regions = [
            ['building', 'Middle East', 'মধ্যপ্রাচ্য', 'Saudi Arabia, UAE, Qatar, Oman, Kuwait, Bahrain and Jordan.', 'সৌদি আরব, সংযুক্ত আরব আমিরাত, কাতার, ওমান, কুয়েত, বাহরাইন ও জর্ডান।'],
            ['compass', 'Asia', 'এশিয়া', 'Thailand, Malaysia, Singapore, India, China, Japan and South Korea.', 'থাইল্যান্ড, মালয়েশিয়া, সিঙ্গাপুর, ভারত, চীন, জাপান ও দক্ষিণ কোরিয়া।'],
            ['globe', 'Europe & UK', 'ইউরোপ ও যুক্তরাজ্য', 'Schengen countries, the United Kingdom and Türkiye.', 'শেনজেনভুক্ত দেশ, যুক্তরাজ্য ও তুরস্ক।'],
            ['plane', 'North America & Oceania', 'উত্তর আমেরিকা ও ওশেনিয়া', 'The United States, Canada, Australia and New Zealand.', 'যুক্তরাষ্ট্র, কানাডা, অস্ট্রেলিয়া ও নিউজিল্যান্ড।'],
        ];

        return [
            'eyebrow' => $this->t('Visa processing', 'ভিসা প্রসেসিং'),
            'title' => $this->t('Visa support for the countries you travel to most', 'যেসব দেশে সবচেয়ে বেশি যান, সেগুলোর ভিসা সহায়তা'),
            'intro' => $this->t('Tourist, business, student and work visas. Not on the list? Ask us; we handle many more.', 'ট্যুরিস্ট, বিজনেস, স্টুডেন্ট ও ওয়ার্ক ভিসা। তালিকায় নেই? জিজ্ঞাসা করুন, আরও অনেক দেশের কাজ করি।'),
            'layout' => 'photos',
            'columns' => '4',
            'icon_style' => 'colorful',
            'items' => array_map(fn ($r) => ['icon' => $r[0], 'title' => $this->t($r[1], $r[2]), 'text' => $this->t($r[3], $r[4]), 'link' => null], $regions),
        ];
    }

    /**
     * Choices of the visa enquiry form's country field.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function visaCountries(): array
    {
        return [
            ['Saudi Arabia', 'সৌদি আরব'], ['United Arab Emirates', 'সংযুক্ত আরব আমিরাত'], ['Qatar', 'কাতার'], ['Oman', 'ওমান'], ['Kuwait', 'কুয়েত'], ['Jordan', 'জর্ডান'],
            ['Thailand', 'থাইল্যান্ড'], ['Malaysia', 'মালয়েশিয়া'], ['Singapore', 'সিঙ্গাপুর'], ['India', 'ভারত'], ['China', 'চীন'], ['Japan', 'জাপান'], ['South Korea', 'দক্ষিণ কোরিয়া'],
            ['Schengen (Europe)', 'শেনজেন (ইউরোপ)'], ['United Kingdom', 'যুক্তরাজ্য'], ['Türkiye', 'তুরস্ক'],
            ['United States', 'যুক্তরাষ্ট্র'], ['Canada', 'কানাডা'], ['Australia', 'অস্ট্রেলিয়া'], ['New Zealand', 'নিউজিল্যান্ড'],
            ['Other', 'অন্যান্য'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function howItWorks(): array
    {
        return [
            'eyebrow' => $this->t('How it works', 'কীভাবে শুরু করবেন'),
            'title' => $this->t('From your first message to the journey home', 'প্রথম বার্তা থেকে দেশে ফেরা পর্যন্ত'),
            'steps' => [
                ['icon' => 'headset', 'title' => $this->t('Tell us your plan', 'পরিকল্পনা জানান'), 'text' => $this->t('Call, WhatsApp or send a form with where, when and how many of you are travelling.', 'কোথায়, কবে ও কতজন যাচ্ছেন—কল, হোয়াটসঅ্যাপ বা ফরমের মাধ্যমে জানান।')],
                ['icon' => 'document', 'title' => $this->t('Get a clear quote', 'স্পষ্ট কোটেশন নিন'), 'text' => $this->t('We send the options with prices in writing, and the documents you will need.', 'অপশন ও মূল্য লিখিতভাবে পাঠাই, সাথে প্রয়োজনীয় কাগজপত্রের তালিকা।')],
                ['icon' => 'wallet', 'title' => $this->t('Confirm and pay', 'নিশ্চিত করুন ও পেমেন্ট দিন'), 'text' => $this->t('Pay only through the company\'s official account or online payment on this website.', 'শুধু কোম্পানির অফিসিয়াল অ্যাকাউন্ট বা এই ওয়েবসাইটের অনলাইন পেমেন্টে পরিশোধ করুন।')],
                ['icon' => 'plane', 'title' => $this->t('Travel with support', 'সহায়তা নিয়ে যাত্রা'), 'text' => $this->t('Tickets, visa and hotel vouchers in hand, and our team a message away.', 'হাতে টিকেট, ভিসা ও হোটেল ভাউচার, আর এক বার্তা দূরে আমাদের টিম।')],
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $qa
     * @return list<array<string, mixed>>
     */
    private function qa(array $qa): array
    {
        return array_map(fn ($q) => ['question' => $this->t($q[0], $q[1]), 'answer' => $this->t($q[2], $q[3])], $qa);
    }

    /**
     * General travel questions (home and contact pages).
     *
     * @return array<string, mixed>
     */
    private function faqContent(): array
    {
        return [
            'eyebrow' => $this->t('Questions', 'প্রশ্নোত্তর'),
            'title' => $this->t('Frequently asked questions', 'সাধারণ জিজ্ঞাসা'),
            'intro' => $this->t('Can\'t find your answer? Call or message us any time.', 'উত্তর খুঁজে পাচ্ছেন না? যেকোনো সময় কল বা বার্তা দিন।'),
            'layout' => 'split',
            'items' => $this->qa([
                ['What services do you offer?', 'আপনারা কী কী সেবা দেন?', '<p>Air tickets, visa processing, tour packages, and Hajj and Umrah through our division Antarik Hajj Kafela. We also book hotels, transfers and travel insurance with any trip.</p>', '<p>এয়ার টিকেট, ভিসা প্রসেসিং, ট্যুর প্যাকেজ এবং আমাদের বিভাগ আন্তরিক হজ্জ কাফেলার মাধ্যমে হজ ও উমরাহ। যেকোনো সফরের সাথে হোটেল, ট্রান্সফার ও ভ্রমণ বিমাও বুক করি।</p>'],
                ['Can you book tickets on any airline?', 'যেকোনো এয়ারলাইনের টিকেট বুক করা যায়?', '<p>Yes. As an IATA accredited agent we issue tickets on every major airline, for domestic and international routes.</p>', '<p>হ্যাঁ। আইএটিএ স্বীকৃত এজেন্ট হিসেবে আমরা অভ্যন্তরীণ ও আন্তর্জাতিক রুটে সব বড় এয়ারলাইনের টিকেট ইস্যু করি।</p>'],
                ['Do you guarantee visa approval?', 'ভিসা হওয়ার নিশ্চয়তা দেন?', '<p>Only the embassy can decide. We make sure your file is complete and correct, and we tell you honestly if we think it is not ready.</p>', '<p>সিদ্ধান্ত শুধু দূতাবাসের। আমরা নিশ্চিত করি আপনার ফাইল সম্পূর্ণ ও নির্ভুল, আর প্রস্তুত না মনে হলে আন্তরিকভাবে জানিয়ে দিই।</p>'],
                ['Can a tour package be changed?', 'ট্যুর প্যাকেজ কি বদলানো যায়?', '<p>Yes. Dates, hotels, the number of days and the places you visit can all be changed, or we can plan a tour from scratch around your budget.</p>', '<p>হ্যাঁ। তারিখ, হোটেল, দিনের সংখ্যা ও দর্শনীয় স্থান—সবই বদলানো যায়, অথবা আপনার বাজেট অনুযায়ী নতুন করে ট্যুর সাজাতে পারি।</p>'],
                ['How should I make payments?', 'পেমেন্ট কীভাবে করব?', '<p>Pay only to Antarik Travel International\'s official account (Current Account No. 2711, Islami Bank Bangladesh Limited, Paltan Branch) or through the online payment on this website. Never pay any individual or other account.</p>', '<p>শুধুমাত্র আন্তরিক ট্রাভেল ইন্টারন্যাশনালের অফিসিয়াল অ্যাকাউন্টে (চলতি হিসাব নং ২৭১১, ইসলামী ব্যাংক বাংলাদেশ লিমিটেড, পল্টন শাখা) অথবা এই ওয়েবসাইটের অনলাইন পেমেন্টে পরিশোধ করুন। কোনো ব্যক্তি বা অন্য অ্যাকাউন্টে পেমেন্ট করবেন না।</p>'],
                ['Do you still arrange Hajj and Umrah?', 'আপনারা কি এখনো হজ ও উমরাহর ব্যবস্থা করেন?', '<p>Yes. Our division Antarik Hajj Kafela (Hajj licence 602, Umrah licence 411) is registering pilgrims for Hajj 2028 and runs Umrah groups throughout the year.</p>', '<p>হ্যাঁ। আমাদের বিভাগ আন্তরিক হজ্জ কাফেলা (হজ লাইসেন্স ৬০২, উমরাহ লাইসেন্স ৪১১) হজ ২০২৮-এর নিবন্ধন নিচ্ছে এবং সারা বছর উমরাহ গ্রুপ পরিচালনা করে।</p>'],
            ]),
            'cta' => $this->link('/contact', 'Contact us', 'যোগাযোগ করুন'),
        ];
    }

    /**
     * Hajj and Umrah questions (the Hajj pre-registration campaign).
     *
     * @return array<string, mixed>
     */
    private function hajjFaq(): array
    {
        return [
            'eyebrow' => $this->t('Questions', 'প্রশ্নোত্তর'),
            'title' => $this->t('Frequently asked questions', 'সাধারণ জিজ্ঞাসা'),
            'intro' => $this->t('Can\'t find your answer? Call or message us any time.', 'উত্তর খুঁজে পাচ্ছেন না? যেকোনো সময় কল বা বার্তা দিন।'),
            'layout' => 'split',
            'items' => $this->qa([
                ['What is Umrah?', 'উমরাহ কী?', '<p>Umrah, meaning "a visit" to the Holy Kaaba, can be performed at any time of the year, unlike Hajj, which has fixed dates. It purifies the heart and brings a Muslim closer to Allah.</p>', '<p>উমরাহ অর্থ পবিত্র কাবা "যিয়ারত"। হজের নির্দিষ্ট সময়ের বিপরীতে উমরাহ বছরের যেকোনো সময় পালন করা যায়। এটি হৃদয়কে পবিত্র করে এবং আল্লাহর নৈকট্য এনে দেয়।</p>'],
                ['Who can perform Umrah?', 'কারা উমরাহ করতে পারেন?', '<p>A Muslim who has reached maturity, is physically and mentally able, and can afford the cost of travel.</p>', '<p>প্রাপ্তবয়স্ক, শারীরিক ও মানসিকভাবে সক্ষম এবং সফরের খরচ বহনে সামর্থ্যবান যেকোনো মুসলিম।</p>'],
                ['What is forbidden in the state of Ihram?', 'ইহরাম অবস্থায় কী কী নিষিদ্ধ?', '<p>Cutting hair or nails, using perfume, marital relations, hunting and, for men, wearing stitched clothing.</p>', '<p>চুল বা নখ কাটা, সুগন্ধি ব্যবহার, দাম্পত্য সম্পর্ক, শিকার এবং পুরুষদের জন্য সেলাই করা পোশাক পরা।</p>'],
                ['How do I pre-register for Hajj?', 'হজের প্রাক-নিবন্ধন কীভাবে করব?', '<p>We are now registering pilgrims for Hajj 2028. Bring a copy of your NID or passport, an active mobile number and your bank account details; we complete the government pre-registration as soon as the Ministry opens the Hajj 2028 window.</p>', '<p>আমরা এখন হজ ২০২৮-এর নিবন্ধন নিচ্ছি। জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি, সক্রিয় মোবাইল নম্বর ও ব্যাংক হিসাবের তথ্য নিয়ে আসুন; মন্ত্রণালয় হজ ২০২৮-এর নিবন্ধন চালু করলেই সরকারি প্রাক-নিবন্ধন আমরা সম্পন্ন করব।</p>'],
                ['How should I make payments?', 'পেমেন্ট কীভাবে করব?', '<p>Pay only to Antarik Travel International\'s official account (Current Account No. 2711, Islami Bank Bangladesh Limited, Paltan Branch). Never pay any individual or other account.</p>', '<p>শুধুমাত্র আন্তরিক ট্রাভেল ইন্টারন্যাশনালের অফিসিয়াল অ্যাকাউন্টে (চলতি হিসাব নং ২৭১১, ইসলামী ব্যাংক বাংলাদেশ লিমিটেড, পল্টন শাখা) পেমেন্ট করুন। কোনো ব্যক্তি বা অন্য অ্যাকাউন্টে পেমেন্ট করবেন না।</p>'],
            ]),
            'cta' => $this->link('/contact', 'Contact us', 'যোগাযোগ করুন'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function policy(string $slug, array $title, array $html): array
    {
        return [
            'type' => Page::TYPE_CUSTOM,
            'system_key' => null,
            'slug' => $slug,
            'title' => $title,
            'sections' => [
                $this->banner($title, $this->t('Last updated '.Dates::format(now(), 'F Y', 'en'), 'সর্বশেষ হালনাগাদ '.Dates::format(now(), 'F Y', 'bn'))),
                ['rich_text', ['body' => $html, 'width' => 'narrow'], ['background' => 'white']],
            ],
        ];
    }

    /**
     * A ready-made (draft) campaign based on the agency's pre-registration drive.
     *
     * @return array<string, mixed>
     */
    private function registrationCampaign(): array
    {
        return [
            'type' => Page::TYPE_CAMPAIGN,
            'system_key' => null,
            'slug' => 'hajj-pre-registration',
            'title' => $this->t('Hajj 2028 pre-registration', 'হজ ২০২৮ প্রাক-নিবন্ধন'),
            'status' => Page::STATUS_DRAFT,
            'focused_layout' => true,
            'sections' => [
                ['hero', [
                    'variant' => 'split',
                    'eyebrow' => $this->t('Hajj 2028 registration is open', 'হজ ২০২৮-এর নিবন্ধন চলছে'),
                    'title' => $this->t('Answer the call of the House of Allah.', 'আল্লাহর ঘরের ডাকে সাড়া দিন।'),
                    'highlight' => $this->t('Register for Hajj 2028 today.', 'আজই হজ ২০২৮-এর নিবন্ধন করুন।'),
                    'text' => $this->t('17, 21, 30 and 42-day Regular, Standard, Premium and VIP packages, guided by experienced scholars.', '১৭, ২১, ৩০ ও ৪২ দিনের রেগুলার, স্ট্যান্ডার্ড, প্রিমিয়াম ও ভিআইপি প্যাকেজ—অভিজ্ঞ আলেমদের তত্ত্বাবধানে।'),
                    'primary' => $this->link('#register', 'Register now', 'এখনই নিবন্ধন করুন'),
                ], []],
                ['lead_form', [
                    'eyebrow' => $this->t('Pre-registration', 'প্রাক-নিবন্ধন'),
                    'title' => $this->t('Take the first step toward your Hajj', 'আপনার হজের প্রথম ধাপ নিন'),
                    'intro' => $this->t('Leave your details and our team will call you to complete the registration.', 'আপনার তথ্য দিন, নিবন্ধন সম্পন্ন করতে আমাদের টিম আপনাকে কল করবে।'),
                    'fields' => [
                        ['field' => 'name', 'required' => true],
                        ['field' => 'phone', 'required' => true],
                        ['field' => 'district', 'required' => true],
                        ['field' => 'thana', 'required' => false],
                        ['field' => 'package', 'required' => false],
                        ['field' => 'email', 'required' => false],
                    ],
                    'package_options' => $this->t("Regular Hajj package\nPremium Hajj package\nVIP Hajj package\nNot sure yet", "রেগুলার হজ প্যাকেজ\nপ্রিমিয়াম হজ প্যাকেজ\nভিআইপি হজ প্যাকেজ\nএখনো নিশ্চিত নই"),
                    'submit_label' => $this->t('Register now', 'নিবন্ধন করুন'),
                    'bullets' => [
                        ['text' => $this->t('Copy of NID or passport', 'জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি')],
                        ['text' => $this->t('An active mobile number', 'সক্রিয় মোবাইল নম্বর')],
                        ['text' => $this->t('A bank account (required for Hajj 2027 registration)', 'ব্যাংক হিসাব (হজ ২০২৭-এর নিবন্ধনে বাধ্যতামূলক ছিল)')],
                        ['text' => $this->t('Government pre-registration deposit, as the Ministry announces it', 'মন্ত্রণালয়ের ঘোষণা অনুযায়ী সরকারি প্রাক-নিবন্ধন জমা')],
                        ['text' => $this->t('VIP Mina tents and an experienced Muallim', 'ভিআইপি মিনা তাঁবু ও অভিজ্ঞ মুয়াল্লিম')],
                    ],
                ], ['anchor' => 'register']],
                ['faq', $this->hajjFaq(), ['background' => 'white']],
            ],
        ];
    }

    private function termsHtml(): string
    {
        return <<<'HTML'
<p>These terms apply to every service booked with Antarik Travel International: air tickets, visa processing, tour packages, and Hajj and Umrah through our division Antarik Hajj Kafela.</p>
<h2>1. Bookings</h2><p>All bookings are subject to availability and are confirmed only after payment and our written confirmation. Prices quoted before confirmation may change with airline fares, hotel rates, exchange rates or government fees.</p>
<h2>2. Payments</h2><p>Pay only to the official account of Antarik Travel International or through the online payment on this website. We are not responsible for money paid to any individual or other account. The full price must be paid before tickets, visas or vouchers are issued, unless agreed otherwise in writing.</p>
<h2>3. Air tickets</h2><p>Tickets follow the fare rules of the airline, which we explain before payment. Date changes, name corrections and refunds are subject to those rules and the airline's charges, plus our service charge. Travellers must check in on time with valid documents.</p>
<h2>4. Visa processing</h2><p>Visas are granted or refused only by the embassy or immigration authority. Our service charge covers preparing and submitting your file and is not refundable if a visa is refused. Embassy, visa centre and courier fees are paid at cost. Refusal caused by incorrect or false information is the applicant's responsibility.</p>
<h2>5. Tour packages</h2><p>Tours run as described in the itinerary. We may change hotels, sightseeing order or transport for reasons beyond our control, with services of a similar or better standard. Optional activities, meals not listed and personal expenses are not included.</p>
<h2>6. Hajj and Umrah</h2><p>Hajj and Umrah packages also follow the rules of the Ministry of Religious Affairs and the Saudi authorities. Pre-registration requires a valid NID or passport copy, an active mobile number and the fee the Ministry announces. Pilgrims attend the workshops of Antarik Hajj Kafela and follow the group's schedule and Muallims.</p>
<h2>7. Cancellations and refunds</h2><p>Cancellations must be made in writing. Refunds, where due, are made after deducting non-refundable costs such as airline penalties, visa fees and hotel charges already paid, and our service charge. No refund is made for unused services once travel has started.</p>
<h2>8. Travel documents and health</h2><p>Travellers must hold a passport valid for at least six months, the visas and vaccinations their destination requires, and travel insurance where advised. We are not liable for denied boarding or entry caused by missing documents.</p>
<h2>9. Force majeure</h2><p>We are not responsible for delays, cancellations or changes caused by natural disasters, political unrest, pandemics, airline schedule changes or any situation beyond our control, but we will help you find the best alternative.</p>
<h2>10. Agreement</h2><p>By booking any service with Antarik Travel International, you agree to these terms and conditions.</p>
HTML;
    }

    private function termsHtmlBn(): string
    {
        return <<<'HTML'
<p>আন্তরিক ট্রাভেল ইন্টারন্যাশনালের মাধ্যমে বুক করা প্রতিটি সেবার ক্ষেত্রে এই শর্তাবলী প্রযোজ্য: এয়ার টিকেট, ভিসা প্রসেসিং, ট্যুর প্যাকেজ এবং আমাদের বিভাগ আন্তরিক হজ্জ কাফেলার মাধ্যমে হজ ও উমরাহ।</p>
<h2>১. বুকিং</h2><p>সকল বুকিং প্রাপ্যতা সাপেক্ষে এবং পেমেন্ট ও আমাদের লিখিত নিশ্চিতকরণের পরই নিশ্চিত হয়। নিশ্চিত হওয়ার আগে জানানো মূল্য এয়ারলাইনের ভাড়া, হোটেলের রেট, বিনিময় হার বা সরকারি ফি অনুযায়ী বদলাতে পারে।</p>
<h2>২. পেমেন্ট</h2><p>শুধুমাত্র আন্তরিক ট্রাভেল ইন্টারন্যাশনালের অফিসিয়াল অ্যাকাউন্টে অথবা এই ওয়েবসাইটের অনলাইন পেমেন্টে পরিশোধ করুন। কোনো ব্যক্তি বা অন্য অ্যাকাউন্টে দেওয়া অর্থের দায় আমাদের নয়। লিখিতভাবে ভিন্ন কিছু সম্মত না হলে টিকেট, ভিসা বা ভাউচার ইস্যুর আগে সম্পূর্ণ মূল্য পরিশোধ করতে হবে।</p>
<h2>৩. এয়ার টিকেট</h2><p>টিকেট এয়ারলাইনের ফেয়ার রুলস অনুযায়ী চলে, যা পেমেন্টের আগেই আমরা জানাই। তারিখ পরিবর্তন, নাম সংশোধন ও রিফান্ড এই নিয়ম ও এয়ারলাইনের চার্জ এবং আমাদের সার্ভিস চার্জ সাপেক্ষে। যাত্রীদের বৈধ কাগজপত্রসহ সময়মতো চেক-ইন করতে হবে।</p>
<h2>৪. ভিসা প্রসেসিং</h2><p>ভিসা মঞ্জুর বা প্রত্যাখ্যানের সিদ্ধান্ত শুধু দূতাবাস বা ইমিগ্রেশন কর্তৃপক্ষের। আমাদের সার্ভিস চার্জ আপনার ফাইল প্রস্তুত ও জমা দেওয়ার জন্য, ভিসা প্রত্যাখ্যাত হলে তা ফেরতযোগ্য নয়। দূতাবাস, ভিসা সেন্টার ও কুরিয়ার ফি প্রকৃত খরচে পরিশোধ করতে হয়। ভুল বা মিথ্যা তথ্যের কারণে প্রত্যাখ্যানের দায় আবেদনকারীর।</p>
<h2>৫. ট্যুর প্যাকেজ</h2><p>ভ্রমণসূচিতে বর্ণিত অনুযায়ী ট্যুর পরিচালিত হয়। আমাদের নিয়ন্ত্রণের বাইরের কারণে হোটেল, দর্শনীয় স্থানের ক্রম বা পরিবহন বদলাতে পারে, তবে সমমানের বা আরও ভালো সেবা দেওয়া হবে। ঐচ্ছিক কার্যক্রম, তালিকায় নেই এমন খাবার ও ব্যক্তিগত খরচ অন্তর্ভুক্ত নয়।</p>
<h2>৬. হজ ও উমরাহ</h2><p>হজ ও উমরাহ প্যাকেজ ধর্ম বিষয়ক মন্ত্রণালয় ও সৌদি কর্তৃপক্ষের নিয়মও মেনে চলে। প্রাক-নিবন্ধনের জন্য বৈধ এনআইডি বা পাসপোর্টের কপি, সচল মোবাইল নম্বর এবং মন্ত্রণালয় ঘোষিত ফি প্রয়োজন। হাজিগণ আন্তরিক হজ্জ কাফেলার কর্মশালায় অংশ নেবেন এবং দলের সময়সূচি ও মুয়াল্লিমদের নির্দেশনা মেনে চলবেন।</p>
<h2>৭. বাতিল ও অর্থ ফেরত</h2><p>বাতিলের অনুরোধ লিখিতভাবে করতে হবে। প্রাপ্য ক্ষেত্রে এয়ারলাইনের জরিমানা, ভিসা ফি ও আগে পরিশোধিত হোটেল চার্জের মতো অফেরতযোগ্য খরচ এবং আমাদের সার্ভিস চার্জ কেটে অর্থ ফেরত দেওয়া হয়। সফর শুরু হওয়ার পর অব্যবহৃত সেবার জন্য কোনো অর্থ ফেরত দেওয়া হয় না।</p>
<h2>৮. ভ্রমণের কাগজপত্র ও স্বাস্থ্য</h2><p>যাত্রীদের অন্তত ছয় মাস মেয়াদি পাসপোর্ট, গন্তব্যের প্রয়োজনীয় ভিসা ও টিকা এবং পরামর্শ অনুযায়ী ভ্রমণ বিমা থাকতে হবে। কাগজপত্রের ঘাটতির কারণে বোর্ডিং বা প্রবেশে বাধার দায় আমাদের নয়।</p>
<h2>৯. অনিবার্য পরিস্থিতি</h2><p>প্রাকৃতিক দুর্যোগ, রাজনৈতিক অস্থিরতা, মহামারি, এয়ারলাইনের সূচি পরিবর্তন বা আমাদের নিয়ন্ত্রণের বাইরের যেকোনো পরিস্থিতিতে বিলম্ব, বাতিল বা পরিবর্তনের জন্য আমরা দায়ী নই, তবে সেরা বিকল্প খুঁজে পেতে আমরা সহায়তা করব।</p>
<h2>১০. সম্মতি</h2><p>আন্তরিক ট্রাভেল ইন্টারন্যাশনালের মাধ্যমে যেকোনো সেবা বুক করে আপনি এই শর্তাবলী মেনে চলতে সম্মত হচ্ছেন।</p>
HTML;
    }

    private function privacyHtmlBn(): string
    {
        return <<<'HTML'
<p>আন্তরিক ট্রাভেল ইন্টারন্যাশনাল আপনার গোপনীয়তাকে সম্মান করে এবং আপনার ব্যক্তিগত তথ্য সুরক্ষায় প্রতিশ্রুতিবদ্ধ। এয়ার টিকেট, ভিসা, ট্যুর, হজ ও উমরাহ সেবা ব্যবহারের সময় আমরা কীভাবে আপনার তথ্য সংগ্রহ, ব্যবহার, সংরক্ষণ ও সুরক্ষা করি, এই নীতিতে তা ব্যাখ্যা করা হয়েছে।</p>
<h2>আমরা যে তথ্য সংগ্রহ করি</h2><ul><li>পূর্ণ নাম, জাতীয়তা ও জন্মতারিখ</li><li>পাসপোর্ট ও এনআইডির তথ্য</li><li>যোগাযোগের তথ্য: ফোন নম্বর, ইমেইল ও ঠিকানা</li><li>ভিসা ফাইলের কাগজপত্র, যেমন ব্যাংক স্টেটমেন্ট ও চাকরির প্রত্যয়নপত্র</li><li>পেমেন্ট ও বিলিংয়ের তথ্য</li><li>স্বাস্থ্য-সংক্রান্ত তথ্য, শুধু গন্তব্য দেশের নিয়মে প্রয়োজন হলে</li></ul>
<h2>আমরা আপনার তথ্য যেভাবে ব্যবহার করি</h2><ul><li>টিকেট বুকিং, ভিসা আবেদন, ট্যুর এবং হজ ও উমরাহ নিবন্ধন সম্পন্ন করতে</li><li>ফ্লাইট, আবাসন ও পরিবহনের ব্যবস্থা করতে</li><li>গুরুত্বপূর্ণ হালনাগাদ তথ্য ও বিজ্ঞপ্তি পাঠাতে</li><li>আপনার নিরাপত্তা নিশ্চিত করতে ও আইনগত বাধ্যবাধকতা মানতে</li><li>আপনার মতামতের ভিত্তিতে আমাদের সেবার মান উন্নত করতে</li></ul>
<h2>তথ্য শেয়ার ও প্রকাশ</h2><p>আপনার সফরের ব্যবস্থা করতে আমরা প্রয়োজনীয় তথ্য এয়ারলাইন, হোটেল, দূতাবাস ও ভিসা সেন্টার, সৌদি কর্তৃপক্ষ এবং ভ্রমণ সহযোগীদের সাথে শেয়ার করি। আমরা কখনো আপনার ব্যক্তিগত তথ্য বিক্রি, ভাড়া বা বিনিময় করি না। আইনগতভাবে প্রয়োজন হলেই কেবল সরকারি বা আইন প্রয়োগকারী সংস্থার কাছে তথ্য প্রকাশ করা হয়।</p>
<h2>সংরক্ষণ ও নিরাপত্তা</h2><p>ব্যক্তিগত তথ্য ও আপলোড করা কাগজপত্র নিরাপদে সংরক্ষণ করা হয় এবং অননুমোদিত প্রবেশ, হারানো বা অপব্যবহার থেকে সুরক্ষিত রাখা হয়। কাগজের নথিপত্র নিরাপদ স্থানে রাখা হয়।</p>
<h2>কুকিজ</h2><p>আপনার অভিজ্ঞতা উন্নত করতে এবং ভাষার মতো পছন্দ মনে রাখতে আমাদের ওয়েবসাইট কুকিজ ব্যবহার করতে পারে। আপনি ব্রাউজারের সেটিংস থেকে কুকিজ বন্ধ করতে পারেন।</p>
<h2>আপনার অধিকার</h2><p>আইনগত বাধ্যবাধকতা সাপেক্ষে আপনি আপনার ব্যক্তিগত তথ্য দেখতে চাইতে, ভুল তথ্য সংশোধনের অনুরোধ করতে বা সম্মতি প্রত্যাহার করতে পারেন।</p>
<h2>তথ্য সংরক্ষণের মেয়াদ</h2><p>সেবা প্রদান ও আইনগত প্রয়োজন পূরণে যতদিন দরকার, শুধু ততদিনই আমরা আপনার তথ্য সংরক্ষণ করি।</p>
<h2>পরিবর্তন ও যোগাযোগ</h2><p>আমরা সময়ে সময়ে এই নীতি হালনাগাদ করতে পারি। গোপনীয়তা নিয়ে যেকোনো প্রশ্নে আমাদের যোগাযোগ পাতায় দেওয়া তথ্য ব্যবহার করে যোগাযোগ করুন।</p>
HTML;
    }

    private function privacyHtml(): string
    {
        return <<<'HTML'
<p>At Antarik Travel International we respect your privacy and are committed to protecting your personal information. This policy explains how we collect, use, store and protect your data when you use our air ticket, visa, tour, Hajj and Umrah services.</p>
<h2>Information we collect</h2><ul><li>Full name, nationality and date of birth</li><li>Passport and NID details</li><li>Contact information: phone number, email and address</li><li>Documents for visa files, such as bank statements and job letters</li><li>Payment and billing details</li><li>Health information, only when your destination's rules require it</li></ul>
<h2>How we use your information</h2><ul><li>To book tickets, process visa applications, tours and Hajj and Umrah registrations</li><li>To arrange flights, accommodation and transport</li><li>To send important updates and notifications</li><li>To ensure your safety and comply with legal requirements</li><li>To improve our services based on your feedback</li></ul>
<h2>Sharing and disclosure</h2><p>We share the information needed to arrange your trip with airlines, hotels, embassies and visa centres, the Saudi authorities and our travel partners. We never sell, rent or trade your personal data. Data is disclosed to government or law-enforcement agencies only when legally required.</p>
<h2>Storage and security</h2><p>Personal information and uploaded documents are stored securely and protected against unauthorised access, loss or misuse. Physical documents are kept in secure facilities.</p>
<h2>Cookies</h2><p>Our website may use cookies to improve your experience and remember preferences such as your language. You can disable cookies in your browser settings.</p>
<h2>Your rights</h2><p>You may request access to your personal information, ask us to correct inaccurate details, or withdraw your consent, subject to legal obligations.</p>
<h2>Retention</h2><p>We keep your data only as long as necessary to provide our services and meet legal requirements.</p>
<h2>Changes and contact</h2><p>We may update this policy from time to time. For any questions about your privacy, please contact us using the details on our contact page.</p>
HTML;
    }
}
