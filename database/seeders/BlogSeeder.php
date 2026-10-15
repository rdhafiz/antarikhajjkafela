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
        $categories = [
            'guides' => PostCategory::create(['name' => $this->t('Guides', 'গাইড'), 'slug' => 'guides', 'sort' => 0])->id,
            'news' => PostCategory::create(['name' => $this->t('News & briefings', 'সংবাদ ও বিজ্ঞপ্তি'), 'slug' => 'news', 'sort' => 1])->id,
        ];
        $author = $this->t('Antarik Hajj Kafela', 'আন্তরিক হজ্জ কাফেলা');

        foreach ($this->posts() as $data) {
            Post::create([
                ...collect($data)->except(['category', 'replaces'])->all(),
                'category_id' => $categories[$data['category']],
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
     * The articles and news, current as of October 2026 (after the Hajj 2027
     * registration window closed). The refresh_blog_posts migration copies
     * them onto existing sites; 'replaces' is an older slug the post took over.
     *
     * @return list<array<string, mixed>>
     */
    public function posts(): array
    {
        return [
            [
                'category' => 'guides',
                'slug' => 'what-is-umrah',
                'published_at' => '2026-10-01 10:00:00',
                'title' => $this->t('What is Umrah? Planning yours in 2026–27', 'উমরাহ কী? ২০২৬–২৭ সালে আপনার উমরাহর পরিকল্পনা'),
                'excerpt' => $this->t(
                    'Umrah, "a visit" to the Holy Kaaba, can be performed at any time of the year. What sets it apart from Hajj, and how to plan your Umrah this season.',
                    'উমরাহ অর্থ পবিত্র কাবার "যিয়ারত"; বছরের যেকোনো সময় তা পালন করা যায়। হজ থেকে এর পার্থক্য এবং এ মৌসুমে কীভাবে উমরাহর পরিকল্পনা করবেন।'
                ),
                'tags' => ['Umrah', 'Beginners', 'Planning'],
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
                        '<h2>Planning your Umrah this season</h2><p>Our Umrah groups travel throughout the year, each with an experienced scholar who guides the group in Bangla. Call us for the next departure date and the seats still available.</p><p>Many of the pilgrims who are preparing for <strong>Hajj 2028</strong> perform Umrah first. It lets you learn Ihram, Tawaf and Sa\'i calmly before the crowds of Hajj, and see the hotels and the holy cities for yourself.</p><p>A passport valid for at least six months is the first thing to arrange. Whether you choose an economy, standard or custom package, Antarik Hajj Kafela is here to make your journey smooth, comfortable and spiritually rewarding.</p>',
                        '<h2>এ মৌসুমে আপনার উমরাহর পরিকল্পনা</h2><p>আমাদের উমরাহ গ্রুপ সারা বছর সফর করে; প্রতিটি দলের সাথে থাকেন একজন অভিজ্ঞ আলেম, যিনি বাংলায় পথ দেখান। পরবর্তী যাত্রার তারিখ ও খালি আসন জানতে আমাদের ফোন করুন।</p><p><strong>হজ ২০২৮</strong>-এর প্রস্তুতি নিচ্ছেন এমন অনেকেই আগে উমরাহ করে নেন। এতে হজের ভিড়ের আগে শান্তভাবে ইহরাম, তাওয়াফ ও সাঈ শেখা যায় এবং হোটেল ও পবিত্র শহরগুলো নিজের চোখে দেখে নেওয়া যায়।</p><p>প্রথম কাজ: অন্তত ছয় মাস মেয়াদি পাসপোর্ট প্রস্তুত রাখা। ইকোনমি, স্ট্যান্ডার্ড বা কাস্টম, যে প্যাকেজই বেছে নিন, আপনার সফরকে সহজ, আরামদায়ক ও আত্মিকভাবে পরিপূর্ণ করতে আন্তরিক হজ্জ কাফেলা পাশে আছে।</p>'
                    ),
                ],
            ],
            [
                'category' => 'guides',
                'slug' => 'how-to-perform-umrah',
                'published_at' => '2026-10-05 10:00:00',
                'title' => $this->t('How to perform Umrah: a step-by-step guide', 'কীভাবে উমরাহ পালন করবেন: ধাপে ধাপে নির্দেশিকা'),
                'excerpt' => $this->t(
                    'From the intention at the Miqat to Tawaf, Sa\'i and Taqsir: the rites of Umrah in order, and what to arrange before you travel.',
                    'মিকাতে নিয়ত থেকে তাওয়াফ, সাঈ ও তাকসির পর্যন্ত: উমরাহর আমলগুলো ধারাবাহিকভাবে, আর সফরের আগে যা গুছিয়ে নেবেন।'
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
                        '<h2>After Umrah</h2><p>Many pilgrims travel on to Madinah to pray in the Prophet\'s Mosque (Masjid an-Nabawi) and send salam to the Prophet (peace be upon him). A visit to the Rawdah needs a permit booked in the Nusuk app; our team helps every pilgrim with it.</p><h2>Learn it before you go</h2><p>Before each group departs we hold an Umrah workshop in Dhaka, where our scholars walk through every step, the duas for each stage and practical travel and health tips. Our scholars then guide the group through each rite in Makkah and Madinah, in Bangla.</p>',
                        '<h2>উমরাহর পরে</h2><p>অনেক হাজি এরপর মদিনায় গিয়ে মসজিদে নববীতে নামাজ আদায় করেন এবং রাসুলুল্লাহ (সাল্লাল্লাহু আলাইহি ওয়াসাল্লাম)-এর প্রতি সালাম পেশ করেন। রিয়াজুল জান্নাহ (রওজা) যিয়ারতের জন্য নুসুক অ্যাপে অনুমতি নিতে হয়; এতে আমাদের টিম প্রত্যেক হাজিকে সহায়তা করে।</p><h2>যাওয়ার আগে শিখে নিন</h2><p>প্রতিটি দল রওনা হওয়ার আগে ঢাকায় আমরা উমরাহ কর্মশালা করি, যেখানে আলেমগণ প্রতিটি ধাপ, প্রতিটি পর্যায়ের দোয়া এবং ভ্রমণ ও স্বাস্থ্য বিষয়ক পরামর্শ বুঝিয়ে দেন। এরপর মক্কা ও মদিনায় প্রতিটি আমলে আলেমগণ বাংলায় দলকে পথ দেখান।</p>'
                    ),
                ],
            ],
            [
                'category' => 'news',
                'slug' => 'important-information-for-hajj-pilgrims',
                'published_at' => '2026-10-10 10:00:00',
                'title' => $this->t('Hajj 2028: what to prepare now, and how to pay safely', 'হজ ২০২৮: এখন যা প্রস্তুত রাখবেন এবং নিরাপদে যেভাবে টাকা দেবেন'),
                'excerpt' => $this->t(
                    'Registration for Hajj 2027 has closed. The documents to prepare for Hajj 2028, how to pay us safely, when prices may change, and our refund policy.',
                    'হজ ২০২৭-এর নিবন্ধন শেষ হয়েছে। হজ ২০২৮-এর জন্য যে কাগজপত্র প্রস্তুত রাখবেন, নিরাপদে টাকা পরিশোধের নিয়ম, কখন মূল্য পরিবর্তন হতে পারে এবং আমাদের ফেরত নীতি।'
                ),
                'tags' => ['Hajj 2028', 'Registration', 'Payment'],
                'blocks' => [
                    $this->text(
                        '<h2>Where things stand</h2><p>Registration for <strong>Hajj 2027</strong> closed on 24 September 2026, and the Ministry of Religious Affairs did not extend it. The next pilgrims to register will travel for <strong>Hajj 2028</strong>, expected in early May 2028 (the exact days depend on the sighting of the moon).</p><p>The Ministry announces each year\'s registration timeline and fees. For Hajj 2027, pre-registration needed a deposit of Tk 30,000 and primary registration Tk 3.5 lakh, through the government e-Hajj system. The Hajj 2028 figures have not been announced yet; we will share them as soon as they are.</p><h2>Prepare these now</h2>',
                        '<h2>বর্তমান অবস্থা</h2><p><strong>হজ ২০২৭</strong>-এর নিবন্ধন ২৪ সেপ্টেম্বর ২০২৬ শেষ হয়েছে; ধর্ম বিষয়ক মন্ত্রণালয় সময় বাড়ায়নি। এখন যাঁরা নিবন্ধন করবেন, তাঁরা যাবেন <strong>হজ ২০২৮</strong>-এ, যা ২০২৮ সালের মে মাসের শুরুতে হওয়ার কথা (সঠিক দিন চাঁদ দেখার ওপর নির্ভর করবে)।</p><p>প্রতি বছরের নিবন্ধনের সময়সূচি ও ফি মন্ত্রণালয় ঘোষণা করে। হজ ২০২৭-এ সরকারি ই-হজ সিস্টেমে প্রাক-নিবন্ধনে ৩০,০০০ টাকা এবং প্রাথমিক নিবন্ধনে ৩ লাখ ৫০ হাজার টাকা জমা দিতে হয়েছিল। হজ ২০২৮-এর অঙ্ক এখনো ঘোষণা হয়নি; ঘোষণা হলেই আমরা জানিয়ে দেব।</p><h2>এখনই যা প্রস্তুত রাখবেন</h2>'
                    ),
                    [
                        'type' => 'list',
                        'style' => 'bullet',
                        'items' => [
                            $this->t('A Bangladeshi passport, valid for at least six months after your return.', 'ফেরার পর অন্তত ছয় মাস মেয়াদ থাকবে এমন বাংলাদেশি পাসপোর্ট।'),
                            $this->t('Your National ID card (or birth certificate) and a recent passport-size photo.', 'জাতীয় পরিচয়পত্র (অথবা জন্ম নিবন্ধন সনদ) এবং সাম্প্রতিক পাসপোর্ট সাইজের ছবি।'),
                            $this->t('An active mobile number, and a bank account: it was required for every pilgrim in the Hajj 2027 registration (family members may share one).', 'একটি সচল মোবাইল নম্বর এবং ব্যাংক হিসাব: হজ ২০২৭-এর নিবন্ধনে প্রত্যেক হজযাত্রীর জন্য এটি বাধ্যতামূলক ছিল (পরিবারের সদস্যরা একটি হিসাব ব্যবহার করতে পারেন)।'),
                            $this->t('Expatriate Bangladeshis also need their residence document for the e-Hajj system.', 'প্রবাসী বাংলাদেশিদের ই-হজ সিস্টেমের জন্য বসবাসের কাগজও লাগবে।'),
                        ],
                    ],
                    $this->text(
                        '<h2>Paying us safely</h2><p>The full package payment must be deposited <strong>only</strong> to <strong>Antarik Travel International, Current Account No. 2711, Islami Bank Bangladesh Limited, Paltan Branch</strong>. Do not make payments to any other account or through any individual or agent.</p><h2>Prices and refunds</h2>',
                        '<h2>নিরাপদে টাকা পরিশোধ</h2><p>প্যাকেজের সম্পূর্ণ অর্থ <strong>শুধুমাত্র</strong> <strong>আন্তরিক ট্রাভেল ইন্টারন্যাশনাল, চলতি হিসাব নং ২৭১১, ইসলামী ব্যাংক বাংলাদেশ লিমিটেড, পল্টন শাখা</strong>-এ জমা দিতে হবে। অন্য কোনো হিসাবে বা কোনো ব্যক্তি বা এজেন্টের মাধ্যমে টাকা দেবেন না।</p><h2>মূল্য ও অর্থ ফেরত</h2>'
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
                'category' => 'news',
                'slug' => 'hajj-2028-registration',
                'replaces' => 'umrah-2025-november-group',
                'published_at' => '2026-10-14 10:00:00',
                'is_featured' => true,
                'title' => $this->t('Hajj 2028: registration open', 'হজ ২০২৮: নিবন্ধন চলছে'),
                'excerpt' => $this->t(
                    'Hajj 2027 registration has closed. Start your Hajj 2028 journey with us now: we prepare your papers and register you as soon as the government window opens.',
                    'হজ ২০২৭-এর নিবন্ধন শেষ। এখনই আমাদের সাথে হজ ২০২৮-এর যাত্রা শুরু করুন: আপনার কাগজপত্র আমরা প্রস্তুত করব এবং সরকারি নিবন্ধন খুললেই আপনাকে নিবন্ধন করিয়ে দেব।'
                ),
                'tags' => ['Hajj 2028', 'Registration'],
                'blocks' => [
                    $this->text(
                        '<p>Registration for Hajj 2027 closed on 24 September 2026. If you missed it, or you are planning ahead, <strong>Antarik Hajj Kafela is now taking registrations for Hajj 2028</strong>, expected in early May 2028.</p><h2>How it works</h2>',
                        '<p>হজ ২০২৭-এর নিবন্ধন ২৪ সেপ্টেম্বর ২০২৬ শেষ হয়েছে। যদি সুযোগটি হাতছাড়া হয়ে থাকে, অথবা আগে থেকেই পরিকল্পনা করছেন, <strong>আন্তরিক হজ্জ কাফেলায় এখন হজ ২০২৮-এর নিবন্ধন চলছে</strong>; হজ হওয়ার কথা ২০২৮ সালের মে মাসের শুরুতে।</p><h2>যেভাবে কাজ হবে</h2>'
                    ),
                    [
                        'type' => 'list',
                        'style' => 'number',
                        'items' => [
                            $this->t('Register with us now: call, message us on WhatsApp or visit our office in Purana Paltan, Dhaka.', 'এখনই আমাদের কাছে নিবন্ধন করুন: ফোন করুন, হোয়াটসঅ্যাপে বার্তা দিন অথবা ঢাকার পুরানা পল্টনে আমাদের অফিসে আসুন।'),
                            $this->t('We check your passport, NID and bank account, and help you get anything missing ready in good time.', 'আপনার পাসপোর্ট, জাতীয় পরিচয়পত্র ও ব্যাংক হিসাব আমরা যাচাই করব এবং যা বাকি আছে তা সময়মতো প্রস্তুত করতে সহায়তা করব।'),
                            $this->t('As soon as the Ministry of Religious Affairs opens the Hajj 2028 window, we complete your pre-registration and registration in the government e-Hajj system.', 'ধর্ম বিষয়ক মন্ত্রণালয় হজ ২০২৮-এর নিবন্ধন চালু করলেই সরকারি ই-হজ সিস্টেমে আপনার প্রাক-নিবন্ধন ও নিবন্ধন আমরা সম্পন্ন করব।'),
                            $this->t('Before departure you join our Hajj workshop, where our scholars prepare you for every rite.', 'যাত্রার আগে আমাদের হজ কর্মশালায় অংশ নেবেন, যেখানে আলেমগণ প্রতিটি আমলের জন্য আপনাকে প্রস্তুত করবেন।'),
                        ],
                    ],
                    $this->text(
                        '<p>Packages for Hajj 2028, from Regular to VIP, will be priced once the government announces the Hajj 2028 costs. Pay only through our official channels, and call us or message us on WhatsApp with any question.</p>',
                        '<p>রেগুলার থেকে ভিআইপি পর্যন্ত হজ ২০২৮-এর প্যাকেজের মূল্য সরকার হজ ২০২৮-এর খরচ ঘোষণার পর নির্ধারণ করা হবে। শুধু আমাদের অফিসিয়াল মাধ্যমে টাকা দিন, আর যেকোনো প্রশ্নে আমাদের ফোন করুন বা হোয়াটসঅ্যাপে বার্তা দিন।</p>'
                    ),
                ],
            ],
        ];
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
