<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentBlocks;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Blog categories and the articles and news from the agency's previous
 * website, plus the live review, gallery and blog sections on the home page.
 */
class BlogSeeder extends Seeder
{
    public function run(ContentBlocks $blocks): void
    {
        if (! PostCategory::exists()) {
            $this->seedPosts($blocks);
        }

        $this->addHomeSections();
    }

    private function seedPosts(ContentBlocks $blocks): void
    {
        $guides = PostCategory::create(['name' => $this->t('Guides', 'গাইড'), 'slug' => 'guides', 'sort' => 0]);
        $news = PostCategory::create(['name' => $this->t('News & briefings', 'সংবাদ ও বিজ্ঞপ্তি'), 'slug' => 'news', 'sort' => 1]);
        $author = $this->t('Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলা');

        $posts = [
            [
                'category_id' => $guides->id,
                'slug' => 'what-is-umrah',
                'published_at' => '2025-08-10 10:00:00',
                'title' => $this->t('What is Umrah?', 'উমরাহ কী?'),
                'excerpt' => $this->t(
                    'Umrah, "a visit" to the Holy Kaaba, can be performed at any time of the year. Here is what makes it different from Hajj.',
                    'উমরাহ অর্থ পবিত্র কাবার "যিয়ারত"; বছরের যেকোনো সময় তা পালন করা যায়। হজ থেকে এর পার্থক্য জেনে নিন।'
                ),
                'tags' => ['Umrah', 'Beginners'],
                'blocks' => [
                    $this->text(
                        '<p>Every year many Muslims from Bangladesh and around the world travel to Saudi Arabia to perform Umrah and fulfil a deeply personal spiritual longing.</p><p>Umrah differs from Hajj. Hajj is the annual pilgrimage to Makkah that must be completed in the first days of the Islamic month of Dhul Hijjah. Umrah, which means "a visit" to the Holy Kaaba, can be performed at any time of the year.</p>',
                        '<p>প্রতি বছর বাংলাদেশসহ সারা বিশ্বের অসংখ্য মুসলমান গভীর আত্মিক আকাঙ্ক্ষা পূরণে উমরাহ পালনের জন্য সৌদি আরবে যান।</p><p>উমরাহ হজ থেকে আলাদা। হজ মক্কায় বার্ষিক তীর্থযাত্রা, যা ইসলামি মাস জিলহজের প্রথম দিনগুলোতে সম্পন্ন করতে হয়। আর উমরাহ, যার অর্থ পবিত্র কাবার "যিয়ারত", বছরের যেকোনো সময় পালন করা যায়।</p>'
                    ),
                    $this->text(
                        '<h2>A journey of the heart</h2><p>Muslims undertake Umrah to recognise Allah as the source of all peace, to turn to Him and to purify their souls. It is a journey of worship, reflection and renewal.</p>',
                        '<h2>হৃদয়ের সফর</h2><p>মুসলমানরা আল্লাহকে সকল শান্তির উৎস হিসেবে স্বীকার করে, তাঁর দিকে ফিরে আসতে এবং আত্মাকে পরিশুদ্ধ করতে উমরাহ পালন করেন। এটি ইবাদত, আত্মচিন্তা ও নবজীবনের সফর।</p>'
                    ),
                    $this->text(
                        '<p>Our Umrah packages are arranged with care, transparency and reliability. Whether you are looking for an economy, standard or custom package, Antarik Hajj Kafela is here to make your pilgrimage smooth, comfortable and spiritually rewarding.</p>',
                        '<p>আমাদের উমরাহ প্যাকেজগুলো যত্ন, স্বচ্ছতা ও নির্ভরযোগ্যতার সাথে সাজানো। ইকোনমি, স্ট্যান্ডার্ড বা কাস্টম, যে প্যাকেজই খুঁজুন, আপনার সফরকে সহজ, আরামদায়ক ও আত্মিকভাবে পরিপূর্ণ করতে আন্তরিক হজ্জ কাফেলা পাশে আছে।</p>'
                    ),
                ],
            ],
            [
                'category_id' => $guides->id,
                'slug' => 'how-to-perform-umrah',
                'published_at' => '2025-08-24 10:00:00',
                'is_featured' => true,
                'title' => $this->t('How to perform Umrah: a step-by-step guide', 'কীভাবে উমরাহ পালন করবেন: ধাপে ধাপে নির্দেশিকা'),
                'excerpt' => $this->t(
                    'From the intention at the Miqat to Tawaf, Sa\'i and Taqsir: the rites of Umrah explained simply, in order.',
                    'মিকাতে নিয়ত থেকে তাওয়াফ, সাঈ ও তাকসির পর্যন্ত: উমরাহর আমলগুলো সহজ ভাষায়, ধারাবাহিকভাবে।'
                ),
                'tags' => ['Umrah', 'Ihram', 'Tawaf'],
                'blocks' => [
                    $this->text(
                        '<p>Performing Umrah brings great spiritual reward. The Prophet Muhammad (peace be upon him) taught that one Umrah to the next is an expiation for the sins in between, and described Hajj and Umrah as a striving in the way of Allah.</p><h2>The steps of Umrah</h2>',
                        '<p>উমরাহ পালনে রয়েছে বিরাট আত্মিক প্রতিদান। নবী মুহাম্মদ (সাল্লাল্লাহু আলাইহি ওয়াসাল্লাম) শিখিয়েছেন, এক উমরাহ থেকে আরেক উমরাহ মধ্যবর্তী গুনাহের কাফফারা; তিনি হজ ও উমরাহকে আল্লাহর পথে প্রচেষ্টা হিসেবে বর্ণনা করেছেন।</p><h2>উমরাহর ধাপসমূহ</h2>'
                    ),
                    [
                        'type' => 'list',
                        'style' => 'number',
                        'items' => [
                            $this->t('Niyyah (intention): before leaving home or on reaching the Miqat, make a sincere intention to perform Umrah for the sake of Allah alone.', 'নিয়ত: ঘর থেকে বের হওয়ার আগে বা মিকাতে পৌঁছে একমাত্র আল্লাহর সন্তুষ্টির জন্য উমরাহর আন্তরিক নিয়ত করুন।'),
                            $this->t('Ihram: men wear two white unstitched sheets; women wear modest clothing that covers the body. Avoid the acts forbidden in Ihram.', 'ইহরাম: পুরুষেরা দুটি সাদা সেলাইবিহীন কাপড় পরবেন; নারীরা শরীর ঢাকা শালীন পোশাক পরবেন। ইহরাম অবস্থায় নিষিদ্ধ কাজগুলো থেকে বিরত থাকুন।'),
                            $this->t('Talbiyah: recite "Labbayk Allahumma Labbayk…" often on the way to Makkah.', 'তালবিয়া: মক্কার পথে বারবার "লাব্বাইক আল্লাহুম্মা লাব্বাইক…" পাঠ করুন।'),
                            $this->t('Tawaf: starting from the Black Stone, walk around the Kaaba seven times, then pray two rak\'ahs behind Maqam Ibrahim if possible.', 'তাওয়াফ: হাজরে আসওয়াদ থেকে শুরু করে কাবা সাতবার প্রদক্ষিণ করুন, এরপর সম্ভব হলে মাকামে ইবরাহিমের পেছনে দুই রাকাত নামাজ পড়ুন।'),
                            $this->t('Sa\'i: walk seven times between Safa and Marwah, making dua and dhikr. Men hurry between the green lights.', 'সাঈ: দোয়া ও যিকিরের সাথে সাফা ও মারওয়ার মাঝে সাতবার চলুন। পুরুষেরা সবুজ বাতির মাঝে দ্রুত চলবেন।'),
                            $this->t('Halq or Taqsir: men shave the head (preferred) or trim the hair evenly; women trim about an inch from the ends.', 'হলক বা তাকসির: পুরুষেরা মাথা মুণ্ডন (উত্তম) বা সমানভাবে চুল ছাঁটবেন; নারীরা চুলের আগা থেকে প্রায় এক ইঞ্চি কাটবেন।'),
                        ],
                    ],
                    $this->text(
                        '<h2>After Umrah</h2><p>Many pilgrims travel on to Madinah to pray in the Prophet\'s Mosque (Masjid an-Nabawi) and send salam to the Prophet (peace be upon him). Our scholars guide every group through each of these steps, in Bangla.</p>',
                        '<h2>উমরাহর পরে</h2><p>অনেক হাজি এরপর মদিনায় গিয়ে মসজিদে নববীতে নামাজ আদায় করেন এবং রাসুলুল্লাহ (সাল্লাল্লাহু আলাইহি ওয়াসাল্লাম)-এর প্রতি সালাম পেশ করেন। আমাদের আলেমগণ প্রতিটি দলকে বাংলায় প্রতিটি ধাপে পথ দেখান।</p>'
                    ),
                ],
            ],
            [
                'category_id' => $news->id,
                'slug' => 'important-information-for-hajj-pilgrims',
                'published_at' => '2025-09-15 10:00:00',
                'title' => $this->t('Important information for Hajj pilgrims', 'হজযাত্রীদের জন্য গুরুত্বপূর্ণ তথ্য'),
                'excerpt' => $this->t(
                    'How to pay for your package safely, when prices may change, and our refund policy.',
                    'নিরাপদে প্যাকেজের টাকা পরিশোধের নিয়ম, কখন মূল্য পরিবর্তন হতে পারে এবং আমাদের ফেরত নীতি।'
                ),
                'tags' => ['Hajj', 'Payment'],
                'blocks' => [
                    $this->text(
                        '<p>The full package payment must be deposited <strong>only</strong> to <strong>Antarik Travel International, Current Account No. 2711, Islami Bank Bangladesh Limited, Paltan Branch</strong>. Do not make payments to any other account or through any individual or agent.</p>',
                        '<p>প্যাকেজের সম্পূর্ণ অর্থ <strong>শুধুমাত্র</strong> <strong>আন্তরিক ট্রাভেল ইন্টারন্যাশনাল, চলতি হিসাব নং ২৭১১, ইসলামী ব্যাংক বাংলাদেশ লিমিটেড, পল্টন শাখা</strong>-এ জমা দিতে হবে। অন্য কোনো হিসাবে বা কোনো ব্যক্তি বা এজেন্টের মাধ্যমে টাকা দেবেন না।</p>'
                    ),
                    [
                        'type' => 'list',
                        'style' => 'bullet',
                        'items' => [
                            $this->t('If the Saudi government suddenly raises costs in any sector, or exchange rates, airfares or Muallim fees change significantly, the package price will be adjusted accordingly.', 'সৌদি সরকার কোনো খাতে হঠাৎ ব্যয় বাড়ালে, অথবা বিনিময় হার, বিমানভাড়া বা মুয়াল্লিম ফি উল্লেখযোগ্যভাবে পরিবর্তিত হলে প্যাকেজ মূল্য সে অনুযায়ী সমন্বয় করা হবে।'),
                            $this->t('If a pilgrim cannot perform Hajj after final registration, only the air ticket price is refunded.', 'চূড়ান্ত নিবন্ধনের পর কেউ হজে যেতে না পারলে শুধু বিমান টিকিটের মূল্য ফেরত দেওয়া হবে।'),
                            $this->t('If the air ticket has already been issued, the refund follows the airline\'s policy after applicable deductions.', 'বিমান টিকিট ইস্যু হয়ে গেলে প্রযোজ্য কর্তন সাপেক্ষে এয়ারলাইনের নীতি অনুযায়ী অর্থ ফেরত দেওয়া হবে।'),
                            $this->t('Flight cancellations or delays, immigration and customs, in-flight services, excess luggage and airport management in Dhaka and Saudi Arabia follow airline and government regulations.', 'ফ্লাইট বাতিল বা বিলম্ব, ইমিগ্রেশন ও কাস্টমস, বিমানের ভেতরের সেবা, অতিরিক্ত লাগেজ এবং ঢাকা ও সৌদি বিমানবন্দরের ব্যবস্থাপনা এয়ারলাইন ও সরকারি বিধি অনুযায়ী পরিচালিত হবে।'),
                        ],
                    ],
                ],
            ],
            [
                'category_id' => $news->id,
                'slug' => 'umrah-2025-november-group',
                'published_at' => '2025-10-01 10:00:00',
                'title' => $this->t('Umrah 2025: November group registration open', 'উমরাহ ২০২৫: নভেম্বর গ্রুপের নিবন্ধন চলছে'),
                'excerpt' => $this->t(
                    'Our November Umrah group travels from 6 to 20 November. Registration is open now.',
                    'আমাদের নভেম্বরের উমরাহ গ্রুপ ৬ থেকে ২০ নভেম্বর সফর করবে। নিবন্ধন এখন চলছে।'
                ),
                'tags' => ['Umrah', 'Group'],
                'blocks' => [
                    $this->text(
                        '<p>Registration is open now! This year\'s November Umrah group will travel from <strong>6 to 20 November</strong>, with an experienced scholar guiding the group throughout.</p><p>Call or message us on WhatsApp to reserve your seat.</p>',
                        '<p>নিবন্ধন এখন চলছে! এ বছরের নভেম্বর উমরাহ গ্রুপ <strong>৬ থেকে ২০ নভেম্বর</strong> সফর করবে; পুরো সময় একজন অভিজ্ঞ আলেম দলকে পথ দেখাবেন।</p><p>আসন নিশ্চিত করতে আমাদের ফোন করুন বা হোয়াটসঅ্যাপে বার্তা দিন।</p>'
                    ),
                ],
            ],
        ];

        foreach ($posts as $data) {
            Post::create([
                ...$data,
                'author_name' => $author,
                'blocks' => $blocks->sanitize($data['blocks']),
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => Carbon::parse($data['published_at']),
                'is_featured' => $data['is_featured'] ?? false,
                'seo' => ['title' => [], 'description' => [], 'image' => null, 'noindex' => false],
            ]);
        }
    }

    /**
     * Reviews, gallery and latest posts on the home page. Each section stays
     * hidden until its module has content.
     */
    private function addHomeSections(): void
    {
        $home = Page::where('system_key', 'home')->first();

        if (! $home || $home->sections()->whereIn('component', ['testimonials', 'gallery_preview', 'latest_posts'])->exists()) {
            return;
        }

        $sections = [
            'testimonials' => [
                'after' => 'process_steps',
                'background' => 'white',
                'content' => [
                    'eyebrow' => $this->t('Pilgrim stories', 'হাজিদের কথা'),
                    'title' => $this->t('In the words of our pilgrims', 'আমাদের হাজিদের মুখে'),
                    'source' => 'featured',
                    'limit' => 3,
                    'cta' => ['url' => '/reviews', 'label' => $this->t('Read all reviews', 'সব রিভিউ পড়ুন')],
                ],
            ],
            'gallery_preview' => [
                'after' => 'testimonials',
                'background' => 'navy-deep',
                'content' => [
                    'eyebrow' => $this->t('Gallery', 'গ্যালারি'),
                    'title' => $this->t('Moments from our journeys', 'আমাদের সফরের মুহূর্ত'),
                    'limit' => '5',
                    'cta' => ['url' => '/gallery', 'label' => $this->t('Open the gallery', 'গ্যালারি দেখুন')],
                ],
            ],
            'latest_posts' => [
                'after' => 'faq',
                'background' => 'white',
                'content' => [
                    'eyebrow' => $this->t('Guides & news', 'গাইড ও সংবাদ'),
                    'title' => $this->t('Prepare for your journey', 'সফরের প্রস্তুতি নিন'),
                    'limit' => 4,
                    'layout' => 'magazine',
                    'cta' => ['url' => '/blog', 'label' => $this->t('All articles', 'সব লেখা')],
                ],
            ],
        ];

        $registry = app(ComponentRegistry::class);
        $processor = app(ContentProcessor::class);

        foreach ($sections as $key => $section) {
            $after = $home->sections()->where('component', $section['after'])->max('sort')
                ?? $home->sections()->where('component', '!=', 'cta')->max('sort')
                ?? 0;
            $home->sections()->where('sort', '>', $after)->increment('sort');

            $home->sections()->create([
                'component' => $key,
                'sort' => $after + 1,
                'content' => $processor->sanitize($registry->get($key)->fields(), $section['content']),
                'style' => SectionStyle::sanitize(['background' => $section['background']]),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $en, string $bn): array
    {
        return ['type' => 'text', 'body' => $this->t($en, $bn)];
    }

    /**
     * @return array{en: string, bn: string}
     */
    private function t(string $en, string $bn): array
    {
        return ['en' => $en, 'bn' => $bn];
    }
}
