<?php

namespace Database\Seeders;

use App\Models\Page;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use Illuminate\Database\Seeder;

/**
 * Default pages (and the policy pages) with real content from the agency's
 * previous website. Only creates pages that don't exist yet, so edits made
 * in the admin portal are never overwritten.
 */
class PageSeeder extends Seeder
{
    public function run(ComponentRegistry $registry, ContentProcessor $processor): void
    {
        foreach ($this->pages() as $definition) {
            $page = Page::firstOrNew(
                $definition['system_key'] ? ['system_key' => $definition['system_key']] : ['slug' => $definition['slug']],
            );

            if ($page->exists) {
                continue;
            }

            $page->fill([
                'type' => $definition['type'],
                'title' => $definition['title'],
                'slug' => $definition['slug'],
                'status' => $definition['status'] ?? Page::STATUS_PUBLISHED,
                'seo' => $definition['seo'] ?? [],
                'focused_layout' => $definition['focused_layout'] ?? false,
            ])->save();

            foreach ($definition['sections'] as $sort => [$component, $content, $style]) {
                $page->sections()->create([
                    'component' => $component,
                    'sort' => $sort,
                    'content' => $processor->sanitize($registry->get($component)->fields(), $content),
                    'style' => SectionStyle::sanitize($style),
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pages(): array
    {
        $pages = [];

        foreach (config('page-builder.default_pages') as $key => $page) {
            $pages[] = [
                'type' => Page::TYPE_DEFAULT,
                'system_key' => $key,
                'slug' => $page['slug'],
                'title' => $page['title'],
                'sections' => match ($key) {
                    'home' => $this->home(),
                    'about' => $this->about(),
                    'services' => $this->services(),
                    'contact' => $this->contact(),
                    default => [
                        $this->banner($page['title'], match ($key) {
                            'gallery' => $this->t('Moments from our Hajj and Umrah journeys, workshops and gatherings.', 'আমাদের হজ ও উমরাহ সফর, কর্মশালা ও আয়োজনের কিছু মুহূর্ত।'),
                            'reviews' => $this->t('In the words of the pilgrims who travelled with us.', 'আমাদের সাথে সফর করা হাজীদের নিজের কথায়।'),
                            default => $this->t('Guidance, news and updates for Hajj and Umrah pilgrims.', 'হজ ও উমরাহ যাত্রীদের জন্য দিকনির্দেশনা, সংবাদ ও আপডেট।'),
                        }),
                        ['page_listing', [], []],
                        $this->cta(),
                    ],
                },
            ];
        }

        $pages[] = $this->policy('terms-and-conditions', $this->t('Terms & conditions', 'শর্তাবলী'), $this->termsHtml());
        $pages[] = $this->policy('privacy-policy', $this->t('Privacy policy', 'গোপনীয়তা নীতি'), $this->privacyHtml());
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
            'eyebrow' => $this->t('Begin your journey', 'আপনার যাত্রা শুরু করুন'),
            'title' => $this->t('Ready to answer the call? Let us plan your Hajj or Umrah together.', 'ডাকে সাড়া দিতে প্রস্তুত? চলুন একসাথে আপনার হজ বা উমরাহর পরিকল্পনা করি।'),
            'text' => $this->t('Speak with our team for honest advice on packages, documents and preparation.', 'প্যাকেজ, কাগজপত্র ও প্রস্তুতি নিয়ে আন্তরিক পরামর্শের জন্য আমাদের টিমের সাথে কথা বলুন।'),
            'contact_buttons' => true,
        ], []];
    }

    /**
     * @return list<array>
     */
    private function home(): array
    {
        return [
            ['hero', [
                'variant' => 'split',
                'eyebrow' => $this->t('Since 2016 · Dhaka, Bangladesh', '২০১৬ থেকে · ঢাকা, বাংলাদেশ'),
                'title' => $this->t('Serving the guests of Allah,', 'আল্লাহর মেহমানদের সেবায়,'),
                'highlight' => $this->t('with care in every step.', 'প্রতিটি ধাপে আন্তরিকতায়।'),
                'text' => $this->t(
                    'Hajj and Umrah journeys focused on your comfort, safety and spiritual fulfilment, guided by experienced scholars from preparation to return.',
                    'আপনার আরাম, নিরাপত্তা ও আত্মিক পরিপূর্ণতাকে গুরুত্ব দিয়ে হজ ও উমরাহ সফর—প্রস্তুতি থেকে ফিরে আসা পর্যন্ত অভিজ্ঞ আলেমদের তত্ত্বাবধানে।'
                ),
                'primary' => $this->link('/hajj', 'Explore Hajj packages', 'হজ প্যাকেজ দেখুন'),
                'secondary' => $this->link('/umrah', 'Umrah packages', 'উমরাহ প্যাকেজ'),
                'highlights' => [
                    ['value' => '1500+', 'label' => $this->t('Journeys completed', 'সম্পন্ন সফর')],
                    ['value' => '500+', 'label' => $this->t('Happy pilgrims', 'সন্তুষ্ট হাজী')],
                    ['value' => '10', 'label' => $this->t('Shariah consultants', 'শরিয়াহ পরামর্শক')],
                    ['value' => '2016', 'label' => $this->t('Serving since', 'সেবা দিয়ে আসছি')],
                ],
            ], []],
            ['about', [
                'eyebrow' => $this->t('Welcome to Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলায় স্বাগতম'),
                'title' => $this->t('A trusted companion for your sacred journey', 'আপনার পবিত্র যাত্রার বিশ্বস্ত সঙ্গী'),
                'body' => $this->t(
                    '<p>Since 2016, Antarik Hajj Kafela has served pilgrims as a reputable Hajj and Umrah agent. We focus on the comfort, safety and spiritual fulfilment of every hajji.</p><p>Our experienced team guides and supports you at every step, from preparation to completion, with transparent dealings and scholars who understand the rulings of Hajj in depth.</p>',
                    '<p>২০১৬ সাল থেকে আন্তরিক হজ্জ কাফেলা একটি সুনামধন্য হজ ও উমরাহ এজেন্ট হিসেবে হাজীদের সেবা দিয়ে আসছে। প্রত্যেক হাজীর আরাম, নিরাপত্তা ও আত্মিক পরিপূর্ণতাই আমাদের মূল লক্ষ্য।</p><p>প্রস্তুতি থেকে শুরু করে হজ সম্পন্ন হওয়া পর্যন্ত আমাদের অভিজ্ঞ টিম প্রতিটি ধাপে আপনার পাশে থাকে—স্বচ্ছ লেনদেন এবং হজের বিধান সম্পর্কে গভীর জ্ঞানসম্পন্ন আলেমদের সাথে।</p>'
                ),
                'years_value' => '9+',
                'years_label' => $this->t('Years of sincere service', 'বছরের আন্তরিক সেবা'),
                'quote' => $this->t('We wish to have the honour of being your trusted companion on this sacred journey of Hajj.', 'হজের এই পবিত্র সফরে আপনার বিশ্বস্ত সঙ্গী হওয়ার সম্মান আমরা পেতে চাই।'),
                'quote_author' => $this->t('Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলা'),
                'link' => $this->link('/about', 'More about us', 'আমাদের সম্পর্কে আরও'),
            ], ['background' => 'white']],
            ['features', [
                'eyebrow' => $this->t('What we offer', 'আমাদের সেবা'),
                'title' => $this->t('Everything your pilgrimage needs, in one place', 'আপনার যাত্রার সব প্রয়োজন, এক জায়গায়'),
                'intro' => $this->t('From the first document to the last ziyarah, our team takes care of the details.', 'প্রথম কাগজপত্র থেকে শেষ যিয়ারাহ পর্যন্ত—সব খুঁটিনাটি আমরা দেখি।'),
                'layout' => 'cards',
                'columns' => '4',
                'items' => $this->serviceItems(short: true),
                'cta' => $this->link('/services', 'All services', 'সব সেবা দেখুন'),
            ], ['background' => 'sand']],
            ['quote', [
                'arabic' => 'وَأَذِّن فِي النَّاسِ بِالْحَجِّ يَأْتُوكَ رِجَالًا وَعَلَىٰ كُلِّ ضَامِرٍ يَأْتِينَ مِن كُلِّ فَجٍّ عَمِيقٍ',
                'text' => $this->t(
                    'And proclaim to the people the Hajj; they will come to you on foot and on every lean camel; they will come from every distant pass.',
                    'আর মানুষের মধ্যে হজের ঘোষণা দাও; তারা তোমার কাছে আসবে পায়ে হেঁটে এবং সব ধরনের কৃশকায় উটের পিঠে চড়ে, দূর-দূরান্তের পথ অতিক্রম করে।'
                ),
                'source' => $this->t('Surah Al-Hajj 22:27', 'সূরা আল-হাজ্জ ২২:২৭'),
            ], ['background' => 'navy']],
            ['features', [
                'eyebrow' => $this->t('Why Antarik', 'কেন আন্তরিক'),
                'title' => $this->t('Hajj, the way it deserves to be performed', 'যেভাবে হজ পালন করা উচিত, ঠিক সেভাবেই'),
                'intro' => $this->t(
                    'Language barriers, lack of guidance and unclear dealings stop many pilgrims from the Hajj they hoped for. We built our service around preventing exactly that.',
                    'ভাষাগত সমস্যা, সঠিক দিকনির্দেশনার অভাব ও অস্বচ্ছ লেনদেনের কারণে অনেকেই কাঙ্ক্ষিত হজ করতে পারেন না। ঠিক এগুলো দূর করতেই আমাদের সেবা সাজানো।'
                ),
                'layout' => 'list',
                'columns' => '2',
                'items' => [
                    ['icon' => 'users', 'title' => $this->t('Qualified, caring management', 'যোগ্য ও আন্তরিক ব্যবস্থাপনা'), 'text' => $this->t('Educated, multilingual and well-mannered people responsible for every part of your journey.', 'শিক্ষিত, বহু ভাষায় দক্ষ ও সদাচারী মানুষেরা আপনার সফরের প্রতিটি অংশের দায়িত্বে।'), 'link' => null],
                    ['icon' => 'book', 'title' => $this->t('Scholars by your side', 'পাশে আছেন আলেমগণ'), 'text' => $this->t('Companionship of scholars with an in-depth, practical understanding of Hajj.', 'হজ সম্পর্কে গভীর ও ব্যবহারিক জ্ঞানসম্পন্ন আলেমদের সান্নিধ্য।'), 'link' => null],
                    ['icon' => 'compass', 'title' => $this->t('Itineraries true to the rulings', 'বিধান মেনে পরিকল্পিত সফর'), 'text' => $this->t('Properly planned schedules that follow the rulings of Hajj and their exact implementation.', 'হজের বিধান ও তার সঠিক বাস্তবায়ন মেনে সুপরিকল্পিত সময়সূচি।'), 'link' => null],
                    ['icon' => 'shield', 'title' => $this->t('Transparent, fair dealings', 'স্বচ্ছ ও ন্যায্য লেনদেন'), 'text' => $this->t('Clear pricing and payments only through the official account of Antarik Travel International.', 'স্পষ্ট মূল্য এবং শুধুমাত্র আন্তরিক ট্রাভেল ইন্টারন্যাশনালের অফিসিয়াল অ্যাকাউন্টে পেমেন্ট।'), 'link' => null],
                ],
            ], ['background' => 'white']],
            ['process_steps', [
                'eyebrow' => $this->t('How it works', 'কীভাবে শুরু করবেন'),
                'title' => $this->t('Four steps to your journey', 'আপনার সফরের চারটি ধাপ'),
                'steps' => [
                    ['icon' => 'compass', 'title' => $this->t('Choose a package', 'প্যাকেজ বাছাই'), 'text' => $this->t('Compare Hajj and Umrah packages, or ask us to suggest one for your budget.', 'হজ ও উমরাহ প্যাকেজ তুলনা করুন, অথবা আপনার বাজেট অনুযায়ী আমাদের পরামর্শ নিন।')],
                    ['icon' => 'passport', 'title' => $this->t('Share your documents', 'কাগজপত্র জমা'), 'text' => $this->t('NID or passport copy and an active mobile number are enough to start.', 'শুরু করতে জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি এবং সক্রিয় মোবাইল নম্বরই যথেষ্ট।')],
                    ['icon' => 'wallet', 'title' => $this->t('Confirm your booking', 'বুকিং নিশ্চিত'), 'text' => $this->t('Pay the advance or registration fee through official channels only.', 'শুধুমাত্র অফিসিয়াল মাধ্যমে অগ্রিম বা নিবন্ধন ফি পরিশোধ করুন।')],
                    ['icon' => 'kaaba', 'title' => $this->t('Prepare and travel', 'প্রস্তুতি ও যাত্রা'), 'text' => $this->t('Attend our workshop, then travel with scholars and guides beside you.', 'আমাদের কর্মশালায় অংশ নিন, তারপর আলেম ও গাইডদের সাথে যাত্রা করুন।')],
                ],
            ], ['background' => 'sand']],
            ['faq', $this->faqContent(), ['background' => 'white']],
            $this->cta(),
        ];
    }

    /**
     * @return list<array>
     */
    private function about(): array
    {
        return [
            $this->banner(
                $this->t('About Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলা সম্পর্কে'),
                $this->t('Affordable, well-guided Hajj and Umrah from Bangladesh since 2016.', '২০১৬ সাল থেকে বাংলাদেশ থেকে সাশ্রয়ী ও সঠিক দিকনির্দেশনায় হজ ও উমরাহ।'),
                $this->t('Our story', 'আমাদের গল্প'),
            ),
            ['about', [
                'eyebrow' => $this->t('Who we are', 'আমরা কারা'),
                'title' => $this->t('Comfort, convenience and value, with sincerity', 'আন্তরিকতার সাথে আরাম, সুবিধা ও মূল্য'),
                'body' => $this->t(
                    '<p>Antarik Hajj Kafela was founded in 2016. Since then, we have provided affordable and budget-friendly Umrah and Hajj packages and built a strong reputation for Islamic tour services that combine comfort, convenience and value.</p><p>We arrange the best Hajj packages along with cost-effective tours and well-chosen flights from Bangladesh, so that you enjoy a smooth, seamless experience at every step.</p>',
                    '<p>আন্তরিক হজ্জ কাফেলা ২০১৬ সালে প্রতিষ্ঠিত। তখন থেকে আমরা সাশ্রয়ী ও বাজেট-বান্ধব হজ ও উমরাহ প্যাকেজ দিয়ে আসছি এবং আরাম, সুবিধা ও মূল্যের সমন্বয়ে ইসলামিক ট্যুর সেবায় সুনাম অর্জন করেছি।</p><p>বাংলাদেশ থেকে সেরা হজ প্যাকেজের পাশাপাশি সাশ্রয়ী ট্যুর ও উপযুক্ত ফ্লাইটের ব্যবস্থা করি, যাতে প্রতিটি ধাপে আপনার অভিজ্ঞতা হয় নির্বিঘ্ন।</p>'
                ),
                'years_value' => '2016',
                'years_label' => $this->t('Founded in Dhaka', 'ঢাকায় প্রতিষ্ঠিত'),
                'quote' => $this->t('Even if you choose not to travel with us, we sincerely offer this advice and our heartfelt prayers for your noble intention.', 'আপনি আমাদের সাথে না গেলেও, আপনার মহৎ নিয়তের জন্য এই পরামর্শ ও আন্তরিক দোয়া রইল।'),
                'quote_author' => $this->t('Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলা'),
            ], ['background' => 'white']],
            ['features', [
                'eyebrow' => $this->t('Our purpose', 'আমাদের লক্ষ্য'),
                'title' => $this->t('Vision & mission', 'ভিশন ও মিশন'),
                'layout' => 'cards',
                'columns' => '2',
                'items' => [
                    ['icon' => 'star', 'title' => $this->t('Vision', 'ভিশন'), 'text' => $this->t('To be the most trusted Hajj travel agency in Bangladesh, making every pilgrim\'s journey comfortable, affordable and spiritually fulfilling.', 'বাংলাদেশের সবচেয়ে বিশ্বস্ত হজ ট্রাভেল এজেন্সি হওয়া—প্রত্যেক হাজীর সফরকে আরামদায়ক, সাশ্রয়ী ও আত্মিকভাবে পরিপূর্ণ করে।'), 'link' => null],
                    ['icon' => 'heart', 'title' => $this->t('Mission', 'মিশন'), 'text' => $this->t('To make your spiritual journey smoother and affordable, with secure flights at a reasonable price and guided services.', 'নিরাপদ ফ্লাইট, যুক্তিসঙ্গত মূল্য ও দিকনির্দেশনামূলক সেবার মাধ্যমে আপনার আত্মিক যাত্রাকে সহজ ও সাশ্রয়ী করা।'), 'link' => null],
                ],
            ], ['background' => 'sand', 'spacing' => 'md']],
            ['image_text', [
                'eyebrow' => $this->t('Why choose us', 'কেন আমাদের বেছে নেবেন'),
                'title' => $this->t('A journey of nearness to Allah, not just a trip', 'শুধু একটি সফর নয়, আল্লাহর নৈকট্যের যাত্রা'),
                'body' => $this->t(
                    '<p>Devout Muslims of Bangladesh hold a deep longing to visit the House of Allah and the beloved city of Madinah. Yet language barriers, lack of knowledge and inadequate support often keep this journey from meeting their hopes.</p><p>We have a team of experienced, knowledgeable scholars dedicated to the management, supervision and guidance of our programmes. Beyond business, our goal is to help the Muslims of Bangladesh perform this act of worship with sincerity.</p>',
                    '<p>বাংলাদেশের ধর্মপ্রাণ মুসলমানদের হৃদয়ে আল্লাহর ঘর ও প্রিয় মদিনা শহর দেখার গভীর আকাঙ্ক্ষা রয়েছে। কিন্তু ভাষাগত বাধা, জ্ঞানের অভাব ও যথাযথ সহায়তার ঘাটতিতে অনেকের এই সফর প্রত্যাশা পূরণ করে না।</p><p>আমাদের রয়েছে অভিজ্ঞ ও জ্ঞানী আলেমদের একটি টিম, যারা আমাদের কার্যক্রমের ব্যবস্থাপনা, তত্ত্বাবধান ও দিকনির্দেশনায় নিয়োজিত। ব্যবসার বাইরেও আমাদের লক্ষ্য বাংলাদেশের মুসলমানদের আন্তরিকতার সাথে এই ইবাদত পালনে সহায়তা করা।</p>'
                ),
                'bullets' => [
                    ['text' => $this->t('Qualified, skilled and well-mannered management', 'যোগ্য, দক্ষ ও সদাচারী ব্যবস্থাপনা')],
                    ['text' => $this->t('Scholars with practical knowledge of Hajj', 'হজের ব্যবহারিক জ্ঞানসম্পন্ন আলেম')],
                    ['text' => $this->t('Itineraries that follow the rulings of Hajj', 'হজের বিধান মেনে সাজানো সফরসূচি')],
                    ['text' => $this->t('Transparent and fair financial dealings', 'স্বচ্ছ ও ন্যায্য আর্থিক লেনদেন')],
                ],
                'image_position' => 'left',
                'primary' => $this->link('/hajj', 'Explore our packages', 'আমাদের প্যাকেজ দেখুন'),
            ], ['background' => 'white']],
            ['team', [
                'eyebrow' => $this->t('Our people', 'আমাদের টিম'),
                'title' => $this->t('Led by people who care about your Hajj', 'যারা আপনার হজের ব্যাপারে আন্তরিক'),
                'members' => [
                    ['name' => $this->t('Mesbah Uddin Saeed', 'মেসবাহ উদ্দিন সাঈদ'), 'role' => $this->t('Chairman', 'চেয়ারম্যান')],
                    ['name' => $this->t('Hafiz Rashedul Islam', 'হাফেজ রাশেদুল ইসলাম'), 'role' => $this->t('Managing Director', 'ব্যবস্থাপনা পরিচালক')],
                    ['name' => $this->t('A.B.M. Azizur Rahman Belal', 'এ.বি.এম. আজিজুর রহমান বেলাল'), 'role' => $this->t('Hajj Director', 'হজ পরিচালক')],
                    ['name' => $this->t('Maulana Hossain Ahmad', 'মাওলানা হোসাইন আহমাদ'), 'role' => $this->t('Official Representative', 'অফিসিয়াল প্রতিনিধি')],
                    ['name' => $this->t('Maulana Saiful Islam', 'মাওলানা সাইফুল ইসলাম'), 'role' => $this->t('Official Representative', 'অফিসিয়াল প্রতিনিধি')],
                ],
            ], ['background' => 'sand']],
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
                $this->t('Visa, flights, hotels, transport and scholarly guidance, arranged by one dedicated team.', 'ভিসা, ফ্লাইট, হোটেল, পরিবহন ও আলেমদের দিকনির্দেশনা—সবকিছু একটি নিবেদিত টিমের মাধ্যমে।'),
                $this->t('What we do', 'আমরা যা করি'),
            ),
            ['features', [
                'eyebrow' => $this->t('Complete pilgrimage support', 'পূর্ণাঙ্গ সহায়তা'),
                'title' => $this->t('Services for Hajj and Umrah', 'হজ ও উমরাহর জন্য সেবা'),
                'layout' => 'cards',
                'columns' => '3',
                'items' => $this->serviceItems(short: false),
            ], ['background' => 'sand']],
            ['process_steps', [
                'eyebrow' => $this->t('Umrah visa', 'উমরাহ ভিসা'),
                'title' => $this->t('How we process your Umrah visa', 'যেভাবে আমরা উমরাহ ভিসা প্রক্রিয়া করি'),
                'intro' => $this->t('Children of all ages need a separate visa; women under 45 travel with a mahram.', 'সব বয়সী শিশুর জন্য আলাদা ভিসা প্রয়োজন; ৪৫ বছরের কম বয়সী নারীদের মাহরামসহ যেতে হয়।'),
                'steps' => [
                    ['icon' => 'document', 'title' => $this->t('Application form', 'আবেদন ফরম'), 'text' => $this->t('We complete and verify the official form with accurate details.', 'সঠিক তথ্য দিয়ে অফিসিয়াল ফরম পূরণ ও যাচাই করি।')],
                    ['icon' => 'passport', 'title' => $this->t('Documents', 'কাগজপত্র'), 'text' => $this->t('Passport valid 6+ months, recent photos and vaccination card.', 'কমপক্ষে ৬ মাস মেয়াদি পাসপোর্ট, সাম্প্রতিক ছবি ও টিকা কার্ড।')],
                    ['icon' => 'users', 'title' => $this->t('Mahram consent', 'মাহরাম সম্মতি'), 'text' => $this->t('Notarised consent letter for women over 45 travelling with a group.', 'গ্রুপের সাথে যাওয়া ৪৫ বছরের বেশি নারীদের জন্য নোটারাইজড সম্মতিপত্র।')],
                    ['icon' => 'check-circle', 'title' => $this->t('Submission', 'জমা ও প্রক্রিয়া'), 'text' => $this->t('We submit everything and keep you updated until your visa is issued.', 'সবকিছু জমা দিয়ে ভিসা হওয়া পর্যন্ত আপনাকে জানাতে থাকি।')],
                ],
            ], ['background' => 'white']],
            $this->cta(),
        ];
    }

    /**
     * @return list<array>
     */
    private function contact(): array
    {
        return [
            $this->banner(
                $this->t('Contact us', 'যোগাযোগ করুন'),
                $this->t('Visit our office, call or send us a message. We are happy to help you plan your journey.', 'আমাদের অফিসে আসুন, কল করুন বা বার্তা পাঠান। আপনার সফর পরিকল্পনায় সাহায্য করতে পেরে আমরা আনন্দিত।'),
                $this->t('We are here for you', 'আমরা আছি আপনার পাশে'),
            ),
            ['contact_info', [
                'eyebrow' => $this->t('Get in touch', 'যোগাযোগ'),
                'title' => $this->t('Talk to our team', 'আমাদের টিমের সাথে কথা বলুন'),
                'intro' => $this->t('For packages, pre-registration, visa or any question about your pilgrimage.', 'প্যাকেজ, প্রাক-নিবন্ধন, ভিসা বা আপনার সফর সম্পর্কিত যেকোনো প্রশ্নে।'),
                'show_map' => true,
            ], ['background' => 'white']],
            ['contact_form', [
                'eyebrow' => $this->t('Send a message', 'বার্তা পাঠান'),
                'title' => $this->t('We will get back to you soon', 'আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব'),
                'intro' => $this->t('Leave your details and our team will call you, In Sha Allah.', 'আপনার তথ্য দিন, ইনশাআল্লাহ আমাদের টিম আপনাকে কল করবে।'),
            ], ['background' => 'sand']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceItems(bool $short): array
    {
        $items = [
            ['passport', 'Umrah visa services', 'উমরাহ ভিসা সেবা', 'Quick, secure visa processing for individuals, families and groups.', 'একক, পরিবার ও গ্রুপের জন্য দ্রুত ও নিরাপদ ভিসা প্রক্রিয়া।', 'Accurate paperwork and up-to-date knowledge of Saudi regulations, for individual, family and group Umrah visas.', 'সৌদি নিয়মকানুন সম্পর্কে হালনাগাদ জ্ঞান ও নির্ভুল কাগজপত্রে একক, পারিবারিক ও গ্রুপ উমরাহ ভিসা।'],
            ['plane', 'Air ticketing', 'এয়ার টিকেটিং', 'Round-trip tickets from Dhaka to Saudi Arabia at competitive fares.', 'প্রতিযোগিতামূলক ভাড়ায় ঢাকা–সৌদি আরব রিটার্ন টিকেট।', 'Round-trip Hajj and Umrah tickets from Dhaka, from budget to premium airlines, with customised seating and preferences.', 'বাজেট থেকে প্রিমিয়াম এয়ারলাইনে ঢাকা থেকে হজ ও উমরাহর রিটার্ন টিকেট, পছন্দমতো আসন ও সুবিধাসহ।'],
            ['hotel', 'Hotels & accommodation', 'হোটেল ও আবাসন', 'Hotels near the Haram in Makkah and Madinah for every budget.', 'সব বাজেটের জন্য মক্কা ও মদিনায় হারামের কাছের হোটেল।', 'From 5-star stays to budget-friendly rooms near Masjid al-Haram and Masjid an-Nabawi, plus organised tents in Mina and Arafat.', 'মসজিদুল হারাম ও মসজিদে নববীর কাছে ৫ তারকা থেকে সাশ্রয়ী আবাসন, সাথে মিনা ও আরাফায় সুব্যবস্থিত তাঁবু।'],
            ['bus', 'Transport', 'পরিবহন', 'Air-conditioned transfers between airports and the holy cities.', 'বিমানবন্দর ও পবিত্র শহরগুলোর মধ্যে শীতাতপ নিয়ন্ত্রিত যাতায়াত।', 'Airport pick-up and drop-off, comfortable buses and private vehicles between Makkah and Madinah, with licensed drivers.', 'এয়ারপোর্ট পিক-আপ ও ড্রপ, মক্কা–মদিনার মধ্যে আরামদায়ক বাস ও প্রাইভেট গাড়ি, লাইসেন্সধারী চালকসহ।'],
            ['document', 'Hajj pre-registration', 'হজ প্রাক-নিবন্ধন', 'Register early with your NID or passport and a mobile number.', 'জাতীয় পরিচয়পত্র বা পাসপোর্ট এবং মোবাইল নম্বর দিয়ে আগেভাগে নিবন্ধন করুন।', 'Secure your place for the coming Hajj with an NID/passport copy, an active mobile number and the government pre-registration fee.', 'জাতীয় পরিচয়পত্র/পাসপোর্টের কপি, সক্রিয় মোবাইল নম্বর ও সরকারি প্রাক-নিবন্ধন ফি দিয়ে আগামী হজে আপনার স্থান নিশ্চিত করুন।'],
            ['book', 'Hajj workshop', 'হজ কর্মশালা', 'Step-by-step guidance on rituals from renowned scholars.', 'প্রখ্যাত আলেমদের কাছ থেকে ধাপে ধাপে হজের নিয়ম শিখুন।', 'Comprehensive workshops on every ritual, supplication and practical matter, led by a panel of renowned scholars and mentors.', 'প্রখ্যাত আলেম ও পরামর্শকদের প্যানেলের পরিচালনায় প্রতিটি রুকন, দোয়া ও ব্যবহারিক বিষয়ে পূর্ণাঙ্গ কর্মশালা।'],
            ['mosque', 'Umrah workshop', 'উমরাহ কর্মশালা', 'Learn Ihram, Tawaf, Sa\'i and Taqsir according to the Sunnah.', 'সুন্নাহ অনুযায়ী ইহরাম, তাওয়াফ, সাঈ ও তাকসীর শিখুন।', 'Ihram, Tawaf, Sa\'i and Halq/Taqsir step by step, with the duas for every stage, travel and health tips.', 'প্রতিটি ধাপের দোয়া, ভ্রমণ ও স্বাস্থ্য পরামর্শসহ ইহরাম, তাওয়াফ, সাঈ ও হলক/তাকসীর ধাপে ধাপে।'],
            ['users', 'Shariah consultants', 'শরিয়াহ পরামর্শক', 'Scholars available to answer your questions before and during Hajj.', 'হজের আগে ও চলাকালীন আপনার প্রশ্নের উত্তরে আলেমগণ।', 'Experienced scholars accompany our groups and help pilgrims perform every rite correctly and with understanding.', 'অভিজ্ঞ আলেমগণ আমাদের দলের সাথে থাকেন এবং প্রতিটি আমল সঠিকভাবে ও বুঝে পালনে সহায়তা করেন।'],
        ];

        return array_map(fn ($i) => [
            'icon' => $i[0],
            'title' => $this->t($i[1], $i[2]),
            'text' => $short ? $this->t($i[3], $i[4]) : $this->t($i[5], $i[6]),
            'link' => null,
        ], $items);
    }

    /**
     * @return array<string, mixed>
     */
    private function faqContent(): array
    {
        $qa = [
            ['What is Umrah?', 'উমরাহ কী?', '<p>Umrah, meaning "a visit" to the Holy Kaaba, can be performed at any time of the year, unlike Hajj, which has fixed dates. It purifies the heart and brings a Muslim closer to Allah.</p>', '<p>উমরাহ অর্থ পবিত্র কাবা "যিয়ারত"। হজের নির্দিষ্ট সময়ের বিপরীতে উমরাহ বছরের যেকোনো সময় পালন করা যায়। এটি হৃদয়কে পবিত্র করে এবং আল্লাহর নৈকট্য এনে দেয়।</p>'],
            ['Who can perform Umrah?', 'কারা উমরাহ করতে পারেন?', '<p>A Muslim who has reached maturity, is physically and mentally able, and can afford the cost of travel.</p>', '<p>প্রাপ্তবয়স্ক, শারীরিক ও মানসিকভাবে সক্ষম এবং সফরের খরচ বহনে সামর্থ্যবান যেকোনো মুসলিম।</p>'],
            ['What is forbidden in the state of Ihram?', 'ইহরাম অবস্থায় কী কী নিষিদ্ধ?', '<p>Cutting hair or nails, using perfume, marital relations, hunting and, for men, wearing stitched clothing.</p>', '<p>চুল বা নখ কাটা, সুগন্ধি ব্যবহার, দাম্পত্য সম্পর্ক, শিকার এবং পুরুষদের জন্য সেলাই করা পোশাক পরা।</p>'],
            ['How do I pre-register for Hajj?', 'হজের প্রাক-নিবন্ধন কীভাবে করব?', '<p>Bring a copy of your NID or passport, an active mobile number and the government pre-registration fee. Our team completes the rest.</p>', '<p>জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি, সক্রিয় মোবাইল নম্বর ও সরকারি প্রাক-নিবন্ধন ফি নিয়ে আসুন। বাকিটা আমাদের টিম সম্পন্ন করবে।</p>'],
            ['How should I make payments?', 'পেমেন্ট কীভাবে করব?', '<p>Pay only to Antarik Travel International\'s official account (Current Account No. 2711, Islami Bank Bangladesh Limited, Paltan Branch). Never pay any individual or other account.</p>', '<p>শুধুমাত্র আন্তরিক ট্রাভেল ইন্টারন্যাশনালের অফিসিয়াল অ্যাকাউন্টে (চলতি হিসাব নং ২৭১১, ইসলামী ব্যাংক বাংলাদেশ লিমিটেড, পল্টন শাখা) পেমেন্ট করুন। কোনো ব্যক্তি বা অন্য অ্যাকাউন্টে পেমেন্ট করবেন না।</p>'],
        ];

        return [
            'eyebrow' => $this->t('Questions', 'প্রশ্নোত্তর'),
            'title' => $this->t('Frequently asked questions', 'সাধারণ জিজ্ঞাসা'),
            'intro' => $this->t('Can\'t find your answer? Call or message us any time.', 'উত্তর খুঁজে পাচ্ছেন না? যেকোনো সময় কল বা বার্তা দিন।'),
            'layout' => 'split',
            'items' => array_map(fn ($q) => ['question' => $this->t($q[0], $q[1]), 'answer' => $this->t($q[2], $q[3])], $qa),
            'cta' => $this->link('/contact', 'Contact us', 'যোগাযোগ করুন'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function policy(string $slug, array $title, string $html): array
    {
        return [
            'type' => Page::TYPE_CUSTOM,
            'system_key' => null,
            'slug' => $slug,
            'title' => $title,
            'sections' => [
                $this->banner($title, $this->t('Last updated '.now()->format('F Y'), 'সর্বশেষ হালনাগাদ '.now()->format('F Y'))),
                ['rich_text', ['body' => ['en' => $html], 'width' => 'narrow'], ['background' => 'white']],
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
            'title' => $this->t('Hajj pre-registration', 'হজ প্রাক-নিবন্ধন'),
            'status' => Page::STATUS_DRAFT,
            'focused_layout' => true,
            'sections' => [
                ['hero', [
                    'variant' => 'split',
                    'eyebrow' => $this->t('Pre-registration is open', 'প্রাক-নিবন্ধন চলছে'),
                    'title' => $this->t('Answer the call of the House of Allah.', 'আল্লাহর ঘরের ডাকে সাড়া দিন।'),
                    'highlight' => $this->t('Register for Hajj today.', 'আজই হজের নিবন্ধন করুন।'),
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
                        ['text' => $this->t('Government pre-registration fee: BDT 30,000', 'সরকারি প্রাক-নিবন্ধন ফি: ৩০,০০০ টাকা')],
                        ['text' => $this->t('VIP Mina tents and an experienced Muallim', 'ভিআইপি মিনা তাঁবু ও অভিজ্ঞ মুয়াল্লিম')],
                    ],
                ], ['anchor' => 'register']],
                ['faq', $this->faqContent(), ['background' => 'white']],
            ],
        ];
    }

    private function termsHtml(): string
    {
        return <<<'HTML'
<h2>1. Booking and registration</h2><p>All bookings are subject to availability and confirmation. Pre-registration requires a valid NID or passport copy, an active mobile number and the initial registration fee as announced. Full payment must be completed before the deadline specified by Antarik Hajj Kafela.</p>
<h2>2. Payment policy</h2><p>Payments can be made in cash, by bank transfer or through other approved methods. The registration fee is non-refundable unless the cancellation is made by Antarik Hajj Kafela due to unavoidable circumstances. The full package cost must be cleared before visa processing and ticket issuance.</p>
<h2>3. Cancellation and refunds</h2><p>Cancellation requests must be made in writing by email or official correspondence. Refunds, where applicable, are processed after deducting non-refundable charges such as visa fees, airline penalties or hotel charges. No refund is issued for cancellations made after visa issuance or flight confirmation.</p>
<h2>4. Travel documents</h2><p>Pilgrims must provide passports valid for at least six months before departure. Visa rejection caused by incorrect or false information is the sole responsibility of the applicant.</p>
<h2>5. Accommodation and transport</h2><p>Hotel categories and room-sharing options are provided as per the chosen package. Transport follows the package details agreed at booking. Antarik Hajj Kafela is not liable for delays or changes caused by airlines, local transport authorities or unforeseen circumstances.</p>
<h2>6. Health and safety</h2><p>Pilgrims are advised to have a medical check-up before travel. Vaccinations required by Saudi regulations are mandatory. Antarik Hajj Kafela is not responsible for pre-existing health conditions or medical emergencies during the pilgrimage.</p>
<h2>7. Responsibilities of pilgrims</h2><p>Pilgrims must follow the laws of Saudi Arabia, attend the Hajj or Umrah workshops organised by Antarik Hajj Kafela, maintain discipline, respect group schedules and cooperate with the team leaders (Muallims).</p>
<h2>8. Force majeure</h2><p>Antarik Hajj Kafela is not responsible for delays, cancellations or changes caused by natural disasters, political unrest, pandemics or any situation beyond our control.</p>
<h2>9. Changes to packages</h2><p>We may modify itineraries, accommodation or transport due to unavoidable circumstances while ensuring similar or better service levels.</p>
<h2>10. Agreement</h2><p>By registering for Hajj or Umrah through Antarik Hajj Kafela, you agree to abide by these terms and conditions.</p>
HTML;
    }

    private function privacyHtml(): string
    {
        return <<<'HTML'
<p>At Antarik Hajj Kafela we respect your privacy and are committed to protecting your personal information. This policy explains how we collect, use, store and protect your data when you use our Hajj and Umrah services.</p>
<h2>Information we collect</h2><ul><li>Full name, nationality and date of birth</li><li>Passport and NID details</li><li>Contact information: phone number, email and home address</li><li>Payment and billing details</li><li>Health information, only when required by Saudi regulations</li></ul>
<h2>How we use your information</h2><ul><li>To process your Hajj and Umrah registration</li><li>To arrange flights, visas, accommodation and transport</li><li>To send important updates and notifications</li><li>To ensure your safety and comply with legal requirements</li><li>To improve our services based on your feedback</li></ul>
<h2>Sharing and disclosure</h2><p>We share necessary information with Saudi authorities, airlines, hotels and travel partners to arrange your pilgrimage. We never sell, rent or trade your personal data. Data is disclosed to government or law-enforcement agencies only when legally required.</p>
<h2>Storage and security</h2><p>Personal information is stored securely and protected against unauthorised access, loss or misuse. Physical documents are kept in secure facilities.</p>
<h2>Cookies</h2><p>Our website may use cookies to improve your experience and remember preferences such as your language. You can disable cookies in your browser settings.</p>
<h2>Your rights</h2><p>You may request access to your personal information, ask us to correct inaccurate details, or withdraw your consent, subject to legal obligations.</p>
<h2>Retention</h2><p>We keep your data only as long as necessary to provide our services and meet legal requirements.</p>
<h2>Changes and contact</h2><p>We may update this policy from time to time. For any questions about your privacy, please contact us using the details on our contact page.</p>
HTML;
    }
}
