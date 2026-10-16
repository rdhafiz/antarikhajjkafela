<?php

namespace Database\Seeders;

use App\Domain\Travel\PackageEditor;
use App\Models\Package;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use Illuminate\Database\Seeder;

/**
 * Services and packages with the real offers from the agency's previous
 * website. Only runs when no services exist yet.
 */
class TravelSeeder extends Seeder
{
    public function run(PackageEditor $editor): void
    {
        if (! Service::exists()) {
            $this->seedTravel($editor);
        }

        $this->addShowcaseToHome();
    }

    /**
     * Put a "featured packages" section on the home page, below the About section.
     */
    private function addShowcaseToHome(): void
    {
        $home = Page::where('system_key', 'home')->first();

        if (! $home || $home->sections()->where('component', 'package_showcase')->exists()) {
            return;
        }

        $after = $home->sections()->where('component', 'about')->value('sort') ?? 0;
        $home->sections()->where('sort', '>', $after)->increment('sort');

        $component = app(ComponentRegistry::class)->get('package_showcase');
        $home->sections()->create([
            'component' => 'package_showcase',
            'sort' => $after + 1,
            'content' => app(ContentProcessor::class)->sanitize($component->fields(), [
                'eyebrow' => $this->t('Our packages', 'আমাদের প্যাকেজ'),
                'title' => $this->t('Choose your journey', 'আপনার সফর বেছে নিন'),
                'intro' => $this->t('Hajj and Umrah packages for every budget, each with scholars beside you.', 'সব বাজেটের হজ ও উমরাহ প্যাকেজ—প্রতিটিতে আলেমগণ আপনার পাশে।'),
                'source' => 'featured',
                'limit' => 3,
                'layout' => 'staggered',
                'cta' => ['url' => '/packages', 'label' => $this->t('View all packages', 'সব প্যাকেজ দেখুন')],
            ]),
            'style' => SectionStyle::sanitize(['background' => 'navy-deep']),
        ]);
    }

    private function seedTravel(PackageEditor $editor): void
    {

        $hajj = Service::create([
            'name' => $this->t('Hajj', 'হজ'),
            'slug' => 'hajj',
            'travel_date' => 'hajj_year',
            'icon' => 'kaaba',
            'sort' => 0,
            'headline' => $this->t('Answer the call of Hajj,', 'হজের ডাকে সাড়া দিন,'),
            'highlight' => $this->t('with scholars beside you.', 'আলেমদের সান্নিধ্যে।'),
            'summary' => $this->t(
                'Hajj packages from Bangladesh with hotels near the Haram, Bangladeshi meals and scholars beside you throughout the journey.',
                'হারামের কাছে হোটেল, বাংলাদেশি খাবার এবং পুরো সফরে আলেমদের সান্নিধ্যসহ বাংলাদেশ থেকে হজ প্যাকেজ।'
            ),
            'description' => $this->t(
                "Since the beginning of human existence, the Holy Kaaba has been the centre of worship for those who worship the One God. Almighty Allah ordained Hajj, which in Arabic means \"to make an intention for visitation\", and every year millions of Muslims answer His call.\n\n\"And proclaim to the people the Hajj; they will come to you on foot and on every lean camel; they will come from every distant pass.\" (Surah Al-Hajj 22:27)",
                "মানব ইতিহাসের শুরু থেকেই পবিত্র কাবা এক আল্লাহর ইবাদতকারীদের কেন্দ্র। মহান আল্লাহ হজ ফরজ করেছেন, আরবিতে যার অর্থ \"যিয়ারতের নিয়ত করা\"; প্রতি বছর লাখো মুসলমান তাঁর ডাকে সাড়া দেন।\n\n\"আর মানুষের মধ্যে হজের ঘোষণা দাও; তারা তোমার কাছে আসবে পায়ে হেঁটে এবং সব ধরনের কৃশকায় উটের পিঠে চড়ে, দূর-দূরান্তের পথ অতিক্রম করে।\" (সূরা আল-হাজ্জ ২২:২৭)"
            ),
        ]);

        $umrah = Service::create([
            'name' => $this->t('Umrah', 'উমরাহ'),
            'slug' => 'umrah',
            'travel_date' => 'month_year',
            'icon' => 'mosque',
            'sort' => 1,
            'headline' => $this->t('Umrah, any time of the year,', 'যেকোনো সময় উমরাহ,'),
            'highlight' => $this->t('guided at every step.', 'প্রতিটি ধাপে দিকনির্দেশনা।'),
            'summary' => $this->t(
                'Umrah packages for every budget, with guidance from an experienced scholar and Ziyarah in Makkah and Madinah.',
                'সব বাজেটের উমরাহ প্যাকেজ—অভিজ্ঞ আলেমের দিকনির্দেশনা এবং মক্কা ও মদিনায় যিয়ারাহসহ।'
            ),
            'description' => $this->t(
                "Umrah is a means of purifying the soul, seeking forgiveness and renewing faith. It can be performed at any time of the year and carries great reward.\n\nThe Prophet Muhammad (peace be upon him) said: \"From one Umrah to another is an expiation for the sins committed between them, and the reward for an accepted Hajj is Paradise.\" (Sahih Bukhari & Muslim)",
                "উমরাহ আত্মশুদ্ধি, ক্ষমা প্রার্থনা ও ঈমান নবায়নের মাধ্যম। বছরের যেকোনো সময় এটি পালন করা যায় এবং এর রয়েছে মহান প্রতিদান।\n\nরাসূলুল্লাহ (সা.) বলেছেন: \"এক উমরাহ থেকে আরেক উমরাহ মধ্যবর্তী গুনাহের কাফফারা, আর কবুল হজের প্রতিদান জান্নাত।\" (সহীহ বুখারী ও মুসলিম)"
            ),
        ]);

        $user = User::role('super-admin')->first() ?? User::first() ?? new User(['id' => null]);

        foreach ($this->packages($hajj, $umrah) as $data) {
            $editor->save(new Package, $data, $user);
        }
    }

    /**
     * @return array<string, string>
     */
    private function t(string $en, string $bn): array
    {
        return ['en' => $en, 'bn' => $bn];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packages(Service $hajj, Service $umrah): array
    {
        $hajjFlights = [[
            'airline' => $this->t('Saudia / Biman Bangladesh', 'সৌদিয়া / বিমান বাংলাদেশ'),
            'schedule' => $this->t('According to the government Hajj flight schedule', 'সরকারি হজ ফ্লাইট সূচি অনুযায়ী'),
            'description' => $this->t('Or another government-approved airline. Return tickets included.', 'অথবা সরকার অনুমোদিত অন্য এয়ারলাইন। রিটার্ন টিকেট অন্তর্ভুক্ত।'),
        ]];

        $hajjSections = [
            $this->listSection('Included', 'অন্তর্ভুক্ত', 'Everything included in the package', 'প্যাকেজে যা যা অন্তর্ভুক্ত', 'check', [
                ['Detailed Hajj guidelines from an experienced scholar before departure', 'যাত্রার আগে অভিজ্ঞ আলেমের বিস্তারিত হজ নির্দেশনা'],
                ['Three meals a day with Bangladeshi cuisine cooked by a Bangladeshi chef', 'বাংলাদেশি বাবুর্চির রান্না দিনে তিন বেলা বাংলাদেশি খাবার'],
                ['Workshops and help performing every rite, with a scholar and guide during the journey', 'সফরে আলেম ও গাইডের উপস্থিতিতে কর্মশালা ও প্রতিটি আমলে সহায়তা'],
                ['Air-conditioned buses: Jeddah–Makkah, Makkah–Madinah, Mina–Arafah–Muzdalifah, Madinah–Jeddah', 'শীতাতপ নিয়ন্ত্রিত বাস: জেদ্দা–মক্কা, মক্কা–মদিনা, মিনা–আরাফাহ–মুজদালিফা, মদিনা–জেদ্দা'],
                ['Ziyarah in Makkah and Madinah supervised by an experienced scholar', 'অভিজ্ঞ আলেমের তত্ত্বাবধানে মক্কা ও মদিনায় যিয়ারাহ'],
                ['Help arranging the sacrifice (Dam-e-Shukr / Qurbani) when needed', 'প্রয়োজনে দম-এ-শুকর / কুরবানির ব্যবস্থায় সহায়তা'],
                ['Guides with you from departure until your return', 'যাত্রা থেকে ফিরে আসা পর্যন্ত গাইডের সার্বক্ষণিক সহায়তা'],
            ]),
            $this->textSection('Please note', 'জেনে রাখুন', 'Important notes', 'গুরুত্বপূর্ণ তথ্য',
                '<p>Dam-e-Shukr (Qurbani) is <strong>not included</strong> in the package price. Men and women stay in separate rooms of 4–5 people; double, triple and family rooms are available at extra cost. Packages can be customised on request.</p><p>Pay only to <strong>Antarik Travel International, Current Account No. 2711, Islami Bank Bangladesh Limited, Paltan Branch</strong>. Prices may change if Saudi government fees, exchange rates, airfares or Muallim fees change.</p>',
                '<p>দম-এ-শুকর (কুরবানি) প্যাকেজ মূল্যে <strong>অন্তর্ভুক্ত নয়</strong>। পুরুষ ও নারীরা ৪–৫ জনের আলাদা কক্ষে থাকবেন; অতিরিক্ত খরচে ডাবল, ট্রিপল ও ফ্যামিলি রুম পাওয়া যায়। চাহিদা অনুযায়ী প্যাকেজ সাজানো যায়।</p><p>শুধুমাত্র <strong>আন্তরিক ট্রাভেল ইন্টারন্যাশনাল, চলতি হিসাব নং ২৭১১, ইসলামী ব্যাংক বাংলাদেশ লিমিটেড, পল্টন শাখা</strong>-য় পেমেন্ট করুন। সৌদি সরকারি ফি, বিনিময় হার, বিমান ভাড়া বা মুয়াল্লিম ফি বাড়লে মূল্য পরিবর্তন হতে পারে।</p>'
            ),
            $this->listSection('Gifts', 'উপহার', 'Complimentary gifts', 'বিনামূল্যে উপহার', 'bullet', [
                ['Ihram cloth for men and a long hijab for women', 'পুরুষদের জন্য ইহরামের কাপড় ও নারীদের জন্য লম্বা হিজাব'],
                ['Handbag for passports and documents', 'পাসপোর্ট ও কাগজপত্রের জন্য হ্যান্ডব্যাগ'],
                ['Cloth bag for Mina and a bag for collecting stones', 'মিনার জন্য কাপড়ের ব্যাগ ও পাথর সংগ্রহের ব্যাগ'],
                ['Main trolley bag', 'প্রধান ট্রলি ব্যাগ'],
                ['Pocket and detailed Hajj guidebooks', 'পকেট ও বিস্তারিত হজ গাইড বই'],
            ]),
        ];

        return [
            [
                'service_id' => $hajj->id,
                'name' => $this->t('Hajj Regular Package', 'হজ রেগুলার প্যাকেজ'),
                'summary' => $this->t('VIP short stay or 35–40 day shifting and permanent packages, with three Bangladeshi meals daily.', 'ভিআইপি স্বল্পমেয়াদি অথবা ৩৫–৪০ দিনের শিফটিং ও পার্মানেন্ট প্যাকেজ, দিনে তিন বেলা বাংলাদেশি খাবারসহ।'),
                'duration_days' => 40,
                'status' => Package::STATUS_PUBLISHED,
                'is_featured' => true,
                'advance_type' => 'none',
                'flights' => $hajjFlights,
                'sections' => $hajjSections,
                'types' => [
                    $this->type('HJ-VIP', 'Hajj VIP Package', 'হজ ভিআইপি প্যাকেজ', 'Short & permanent package, 12–15 days.', 'স্বল্পমেয়াদি ও পার্মানেন্ট প্যাকেজ, ১২–১৫ দিন।', [
                        $this->price('4-bed shared room', '৪ বেডের শেয়ার্ড রুম', 'Without meals.', 'খাবার ছাড়া।', 1200000, [
                            $this->hotel('Makkah', 'মক্কা', 'Zamzam Tower / Makkah Tower', 'জমজম টাওয়ার / মক্কা টাওয়ার', 'Adjacent to the Masjid al-Haram courtyard.', 'মসজিদুল হারামের চত্বরের একদম পাশে।'),
                            $this->hotel('Madinah', 'মদিনা', '5-star hotel', '৫ তারকা হোটেল', 'Within 150 metres of the Masjid an-Nabawi courtyard.', 'মসজিদে নববীর চত্বর থেকে সর্বোচ্চ ১৫০ মিটার।'),
                        ]),
                    ]),
                    $this->type('HJ-A', 'Hajj Package A (Shifting)', 'হজ প্যাকেজ এ (শিফটিং)', 'Shifting package, 35–40 days, three Bangladeshi meals daily.', 'শিফটিং প্যাকেজ, ৩৫–৪০ দিন, দিনে তিন বেলা বাংলাদেশি খাবার।', [
                        $this->price('4/5-bed shared room', '৪/৫ বেডের শেয়ার্ড রুম', 'Three meals daily with Bangladeshi cuisine.', 'দিনে তিন বেলা বাংলাদেশি খাবার।', 619999, [
                            $this->hotel('Makkah', 'মক্কা', 'Standard hotel', 'স্ট্যান্ডার্ড হোটেল', 'Within 400 metres of the Masjid al-Haram courtyard.', 'মসজিদুল হারামের চত্বর থেকে সর্বোচ্চ ৪০০ মিটার।'),
                            $this->hotel('Madinah', 'মদিনা', 'Star-rated hotel', 'স্টার মানের হোটেল', 'Within 300 metres of the Masjid an-Nabawi courtyard.', 'মসজিদে নববীর চত্বর থেকে সর্বোচ্চ ৩০০ মিটার।'),
                        ]),
                    ]),
                    $this->type('HJ-B', 'Hajj Package B', 'হজ প্যাকেজ বি', 'Permanent package, 35–40 days, without meals.', 'পার্মানেন্ট প্যাকেজ, ৩৫–৪০ দিন, খাবার ছাড়া।', [
                        $this->price('4/5-bed shared room', '৪/৫ বেডের শেয়ার্ড রুম', 'Without meals.', 'খাবার ছাড়া।', 483000, [
                            $this->hotel('Makkah', 'মক্কা', 'Standard hotel / house', 'স্ট্যান্ডার্ড হোটেল / বাসা', 'Within 3,000 metres of the Masjid al-Haram courtyard.', 'মসজিদুল হারামের চত্বর থেকে সর্বোচ্চ ৩,০০০ মিটার।'),
                            $this->hotel('Madinah', 'মদিনা', 'Standard hotel / house', 'স্ট্যান্ডার্ড হোটেল / বাসা', 'Within 1,500 metres of the Masjid an-Nabawi courtyard.', 'মসজিদে নববীর চত্বর থেকে সর্বোচ্চ ১,৫০০ মিটার।'),
                        ]),
                    ]),
                ],
            ],
            [
                'service_id' => $hajj->id,
                'name' => $this->t('Hajj Premium Package', 'হজ প্রিমিয়াম প্যাকেজ'),
                'summary' => $this->t('Permanent packages closer to the Haram, with buffet or Bangladeshi meals and star-rated hotels in Madinah.', 'হারামের আরও কাছে পার্মানেন্ট প্যাকেজ, বুফে বা বাংলাদেশি খাবার এবং মদিনায় স্টার মানের হোটেল।'),
                'duration_days' => 35,
                'status' => Package::STATUS_PUBLISHED,
                'is_featured' => true,
                'advance_type' => 'none',
                'flights' => $hajjFlights,
                'sections' => $hajjSections,
                'types' => [
                    $this->type('HP-PREMIUM', 'Hajj Premium Package', 'হজ প্রিমিয়াম প্যাকেজ', 'Permanent package, 30–35 days, three buffet meals (Bangladeshi food) daily.', 'পার্মানেন্ট প্যাকেজ, ৩০–৩৫ দিন, দিনে তিন বেলা বুফে (বাংলাদেশি খাবার)।', [
                        $this->price('4/5-bed sharing room', '৪/৫ বেডের শেয়ারিং রুম', 'Three buffet meals daily.', 'দিনে তিন বেলা বুফে খাবার।', 900000, [
                            $this->hotel('Makkah', 'মক্কা', '3-star hotel', '৩ তারকা হোটেল', 'Within 300 metres of Masjid al-Haram.', 'মসজিদুল হারাম থেকে সর্বোচ্চ ৩০০ মিটার।'),
                            $this->hotel('Madinah', 'মদিনা', 'Star-rated hotel', 'স্টার মানের হোটেল', 'Within 100 metres of Masjid an-Nabawi.', 'মসজিদে নববী থেকে সর্বোচ্চ ১০০ মিটার।'),
                        ]),
                    ]),
                    $this->type('HP-A', 'Premium Package A', 'প্রিমিয়াম প্যাকেজ এ', 'Permanent package, 35–40 days, three Bangladeshi meals daily.', 'পার্মানেন্ট প্যাকেজ, ৩৫–৪০ দিন, দিনে তিন বেলা বাংলাদেশি খাবার।', [
                        $this->price('4/5-bed shared room', '৪/৫ বেডের শেয়ার্ড রুম', 'Three Bangladeshi meals daily.', 'দিনে তিন বেলা বাংলাদেশি খাবার।', 719998, [
                            $this->hotel('Makkah', 'মক্কা', 'Standard hotel', 'স্ট্যান্ডার্ড হোটেল', 'Within 600 metres of the Masjid al-Haram courtyard.', 'মসজিদুল হারামের চত্বর থেকে সর্বোচ্চ ৬০০ মিটার।'),
                            $this->hotel('Madinah', 'মদিনা', 'Star-rated hotel', 'স্টার মানের হোটেল', 'Within 250 metres of the Masjid an-Nabawi courtyard.', 'মসজিদে নববীর চত্বর থেকে সর্বোচ্চ ২৫০ মিটার।'),
                        ]),
                    ]),
                    $this->type('HP-B', 'Premium Package B', 'প্রিমিয়াম প্যাকেজ বি', 'Permanent package, 35–40 days, three Bangladeshi meals daily.', 'পার্মানেন্ট প্যাকেজ, ৩৫–৪০ দিন, দিনে তিন বেলা বাংলাদেশি খাবার।', [
                        $this->price('4/5-bed shared room', '৪/৫ বেডের শেয়ার্ড রুম', 'Three Bangladeshi meals daily.', 'দিনে তিন বেলা বাংলাদেশি খাবার।', 620000, [
                            $this->hotel('Makkah', 'মক্কা', 'Standard hotel', 'স্ট্যান্ডার্ড হোটেল', 'Within 1,000 metres of the Masjid al-Haram courtyard.', 'মসজিদুল হারামের চত্বর থেকে সর্বোচ্চ ১,০০০ মিটার।'),
                            $this->hotel('Madinah', 'মদিনা', 'Star-rated hotel', 'স্টার মানের হোটেল', 'Within 300 metres of the Masjid an-Nabawi courtyard.', 'মসজিদে নববীর চত্বর থেকে সর্বোচ্চ ৩০০ মিটার।'),
                        ]),
                    ]),
                ],
            ],
            [
                'service_id' => $umrah->id,
                'name' => $this->t('Umrah Package', 'উমরাহ প্যাকেজ'),
                'summary' => $this->t('7 and 14-day Umrah with hotels from five-star (0–100 m) to standard, Bangladeshi meals and scholar-led Ziyarah.', '৭ ও ১৪ দিনের উমরাহ—পাঁচ তারকা (০–১০০ মিটার) থেকে স্ট্যান্ডার্ড হোটেল, বাংলাদেশি খাবার ও আলেমের নেতৃত্বে যিয়ারাহ।'),
                'duration_days' => 14,
                'status' => Package::STATUS_PUBLISHED,
                'is_featured' => true,
                'advance_type' => 'none',
                'flights' => [[
                    'airline' => $this->t('Direct / transit flights', 'সরাসরি / ট্রানজিট ফ্লাইট'),
                    'schedule' => $this->t('Group departures throughout the year', 'সারা বছর গ্রুপ যাত্রা'),
                    'description' => $this->t('Return flights from Dhaka included.', 'ঢাকা থেকে রিটার্ন ফ্লাইট অন্তর্ভুক্ত।'),
                ]],
                'sections' => [
                    $this->listSection('Included', 'অন্তর্ভুক্ত', 'Everything included in the package', 'প্যাকেজে যা যা অন্তর্ভুক্ত', 'check', [
                        ['Detailed Umrah guidelines by an experienced scholar before departure', 'যাত্রার আগে অভিজ্ঞ আলেমের বিস্তারিত উমরাহ নির্দেশনা'],
                        ['Three meals a day with Bangladeshi cuisine cooked by a Bangladeshi chef', 'বাংলাদেশি বাবুর্চির রান্না দিনে তিন বেলা বাংলাদেশি খাবার'],
                        ['A scholar and guide with you for discussions and performing Umrah', 'আলোচনা ও উমরাহ পালনে সঙ্গে আলেম ও গাইড'],
                        ['Air-conditioned buses: Jeddah–Makkah, Makkah–Madinah, Madinah–Airport', 'শীতাতপ নিয়ন্ত্রিত বাস: জেদ্দা–মক্কা, মক্কা–মদিনা, মদিনা–এয়ারপোর্ট'],
                        ['Ziyarah of 15+ sites in Makkah and 10+ in Madinah with a scholar', 'আলেমের সাথে মক্কায় ১৫টির বেশি ও মদিনায় ১০টির বেশি স্থানে যিয়ারাহ'],
                        ['Guides with you from departure until your return', 'যাত্রা থেকে ফিরে আসা পর্যন্ত গাইডের সার্বক্ষণিক সহায়তা'],
                    ]),
                    $this->listSection('Gifts', 'উপহার', 'Complimentary gifts', 'বিনামূল্যে উপহার', 'bullet', [
                        ['Long hijab for women', 'নারীদের জন্য লম্বা হিজাব'],
                        ['Fabric bag for light items', 'হালকা জিনিসের জন্য কাপড়ের ব্যাগ'],
                        ['Pocket guidebook', 'পকেট গাইড বই'],
                    ]),
                ],
                'types' => [
                    $this->type('UM-A', 'Package A · Standard', 'প্যাকেজ এ · স্ট্যান্ডার্ড', '7 days.', '৭ দিন।', [
                        $this->price('Shared room (4–5 people)', 'শেয়ার্ড রুম (৪–৫ জন)', 'Double, triple and family rooms at extra cost.', 'অতিরিক্ত খরচে ডাবল, ট্রিপল ও ফ্যামিলি রুম।', 160000, [
                            $this->hotel('Makkah', 'মক্কা', 'Any five-star hotel', 'যেকোনো পাঁচ তারকা হোটেল', 'Within 0–100 metres.', '০–১০০ মিটারের মধ্যে।'),
                            $this->hotel('Madinah', 'মদিনা', 'Any five-star hotel', 'যেকোনো পাঁচ তারকা হোটেল', 'Within 0–100 metres.', '০–১০০ মিটারের মধ্যে।'),
                        ]),
                    ]),
                    $this->type('UM-B', 'Package B · Regular', 'প্যাকেজ বি · রেগুলার', '14 days.', '১৪ দিন।', [
                        $this->price('Shared room (4–5 people)', 'শেয়ার্ড রুম (৪–৫ জন)', 'Double, triple and family rooms at extra cost.', 'অতিরিক্ত খরচে ডাবল, ট্রিপল ও ফ্যামিলি রুম।', 145000, [
                            $this->hotel('Makkah', 'মক্কা', 'Any star-rated hotel', 'যেকোনো স্টার মানের হোটেল', 'Within 300 metres.', '৩০০ মিটারের মধ্যে।'),
                            $this->hotel('Madinah', 'মদিনা', 'Any star-rated hotel', 'যেকোনো স্টার মানের হোটেল', 'Within 300 metres.', '৩০০ মিটারের মধ্যে।'),
                        ]),
                    ]),
                    $this->type('UM-C', 'Package C · Basic', 'প্যাকেজ সি · বেসিক', '14 days.', '১৪ দিন।', [
                        $this->price('Shared room (4–5 people)', 'শেয়ার্ড রুম (৪–৫ জন)', 'Double, triple and family rooms at extra cost.', 'অতিরিক্ত খরচে ডাবল, ট্রিপল ও ফ্যামিলি রুম।', 135000, [
                            $this->hotel('Makkah', 'মক্কা', 'Standard hotel / house', 'স্ট্যান্ডার্ড হোটেল / বাসা', 'Within 600 metres.', '৬০০ মিটারের মধ্যে।'),
                            $this->hotel('Madinah', 'মদিনা', 'Standard hotel / house', 'স্ট্যান্ডার্ড হোটেল / বাসা', 'Within 350 metres.', '৩৫০ মিটারের মধ্যে।'),
                        ]),
                    ]),
                ],
            ],
        ];
    }

    private function type(string $code, string $en, string $bn, string $descEn, string $descBn, array $prices): array
    {
        return ['code' => $code, 'name' => $this->t($en, $bn), 'short_description' => $this->t($descEn, $descBn), 'prices' => $prices];
    }

    private function price(string $en, string $bn, string $descEn, string $descBn, int $amount, array $hotels): array
    {
        return ['title' => $this->t($en, $bn), 'short_description' => $this->t($descEn, $descBn), 'price' => $amount, 'unit' => 'person', 'hotels' => $hotels];
    }

    private function hotel(string $en, string $bn, string $subEn, string $subBn, string $descEn, string $descBn): array
    {
        return ['heading' => $this->t($en, $bn), 'subheading' => $this->t($subEn, $subBn), 'description' => $this->t($descEn, $descBn)];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items
     */
    private function listSection(string $badgeEn, string $badgeBn, string $headingEn, string $headingBn, string $style, array $items): array
    {
        return [
            'layout' => 'full',
            'badge' => $this->t($badgeEn, $badgeBn),
            'heading' => $this->t($headingEn, $headingBn),
            'blocks' => [[
                'type' => 'list',
                'style' => $style,
                'items' => array_map(fn ($i) => $this->t($i[0], $i[1]), $items),
            ]],
        ];
    }

    private function textSection(string $badgeEn, string $badgeBn, string $headingEn, string $headingBn, string $htmlEn, string $htmlBn): array
    {
        return [
            'layout' => 'full',
            'badge' => $this->t($badgeEn, $badgeBn),
            'heading' => $this->t($headingEn, $headingBn),
            'blocks' => [['type' => 'text', 'body' => $this->t($htmlEn, $htmlBn)]],
        ];
    }
}
