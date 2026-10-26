<?php

namespace Database\Seeders;

use App\Domain\Settings\Settings;
use App\Models\Media;
use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Database\Seeders\Concerns\ImportsSeedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Search titles, descriptions and social share images (1200×630 JPEG, cropped
 * from the Unsplash-licensed photos in images/heroes and images/travel, see docs/content/image-credits.md)
 * for every page, service, package and post. Only empty values are filled,
 * so anything edited in the admin is kept.
 */
class SeoSeeder extends Seeder
{
    use ImportsSeedImages;

    /**
     * Share photos: file => [English alt, Bangla alt].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PHOTOS = [
        'home-haram.webp' => ['Pilgrims at Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারামে হাজিগণ'],
        'madinah-golden.webp' => ['The Prophet\'s Mosque in Madinah at sunset', 'সূর্যাস্তে মদিনার মসজিদে নববী'],
        'makkah-hotels.webp' => ['Hotels around the Haram in Makkah', 'মক্কায় হারামের আশপাশের হোটেল'],
        'madinah-umbrellas.webp' => ['The courtyard umbrellas of the Prophet\'s Mosque in Madinah', 'মসজিদে নববীর আঙিনার ছাতা'],
        'haram-courtyard.webp' => ['Pilgrims around the Kaaba in Masjid al-Haram', 'মসজিদুল হারামে কাবার চারপাশে হাজিগণ'],
        'quran.webp' => ['The Holy Quran on a wooden stand', 'কাঠের রেহালে পবিত্র কুরআন'],
        'green-dome.webp' => ['The Green Dome of the Prophet\'s Mosque', 'মসজিদে নববীর সবুজ গম্বুজ'],
        'nabawi-colonnade.webp' => ['A colonnade of the Prophet\'s Mosque in Madinah', 'মসজিদে নববীর স্তম্ভসারি'],
        'hajj-campaign.webp' => ['Hajj pilgrims in Makkah', 'মক্কায় হজযাত্রীগণ'],
        'hajj-crowd.webp' => ['Hajj pilgrims around the Kaaba', 'কাবার চারপাশে হজযাত্রীগণ'],
        'haram-wide.webp' => ['Masjid al-Haram in Makkah', 'মক্কার মসজিদুল হারাম'],
        'hajj-regular.webp' => ['Pilgrims in the courtyard of Masjid al-Haram', 'মসজিদুল হারামের আঙিনায় হাজিগণ'],
        'hajj-premium.webp' => ['The Kaaba with pilgrims around it', 'কাবা ও চারপাশে হাজিগণ'],
        'travel/travellers.webp' => ['A family walking to a small plane on a sunny airstrip', 'রোদেলা এয়ারস্ট্রিপে ছোট বিমানের দিকে হেঁটে যাওয়া একটি পরিবার'],
        'travel/airport.webp' => ['Travellers in a bright airport terminal', 'উজ্জ্বল এয়ারপোর্ট টার্মিনালে ভ্রমণকারীরা'],
        'travel/airplane-sky.webp' => ['An airliner wing above the clouds', 'মেঘের ওপরে উড়োজাহাজের ডানা'],
        'travel/passport-visa.webp' => ['Passports ready for a visa application', 'ভিসা আবেদনের জন্য প্রস্তুত পাসপোর্ট'],
    ];

    public function run(Settings $settings): void
    {
        $this->pages();
        $this->services();
        $this->packages();
        $this->posts();

        // Default share image for pages without their own.
        if (blank($settings->get('seo.og_image'))) {
            $settings->set('seo', ['og_image' => $this->share('travel/travellers.webp')->id]);
        }
    }

    private function pages(): void
    {
        $pages = [
            'home' => ['travel/travellers.webp',
                ['Hajj, Umrah, Air Tickets, Visa & Tour Packages from Dhaka', 'ঢাকা থেকে হজ, উমরাহ, এয়ার টিকেট, ভিসা ও ট্যুর প্যাকেজ'],
                ['Trusted international travel agency in Dhaka since 2016: air tickets on every airline, visa processing, holiday tours and scholar-guided Hajj and Umrah.',
                    '২০১৬ সাল থেকে ঢাকার বিশ্বস্ত আন্তর্জাতিক ট্রাভেল এজেন্সি: সব এয়ারলাইনের টিকেট, ভিসা প্রসেসিং, হলিডে ট্যুর এবং আলেমদের তত্ত্বাবধানে হজ ও উমরাহ।']],
            'about' => ['travel/travellers.webp',
                ['About Us: Our Story, Team & Licences', 'আমাদের সম্পর্কে: গল্প, টিম ও লাইসেন্স'],
                ['Antarik Travel International began in Dhaka in 2016 as a Hajj and Umrah agency and now arranges air tickets, visas and tours worldwide. Meet our team.',
                    'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল ২০১৬ সালে ঢাকায় হজ ও উমরাহ এজেন্সি হিসেবে যাত্রা শুরু করে, এখন সারা বিশ্বের এয়ার টিকেট, ভিসা ও ট্যুরের ব্যবস্থা করে। আমাদের টিমকে জানুন।']],
            'services' => ['travel/airport.webp',
                ['Travel Services: Air Tickets, Visa, Tours, Hajj & Umrah', 'ট্রাভেল সেবা: এয়ার টিকেট, ভিসা, ট্যুর, হজ ও উমরাহ'],
                ['Air tickets, visa processing, tour packages, Hajj and Umrah, plus hotels, transfers and support on the road, arranged by one licensed team in Dhaka.',
                    'এয়ার টিকেট, ভিসা প্রসেসিং, ট্যুর প্যাকেজ, হজ ও উমরাহ, সাথে হোটেল, ট্রান্সফার ও সফরে সহায়তা—ঢাকার একটি লাইসেন্সপ্রাপ্ত টিমের মাধ্যমে।']],
            'contact' => ['travel/airport.webp',
                ['Contact Us: Call, WhatsApp or Visit', 'যোগাযোগ: কল, হোয়াটসঅ্যাপ বা অফিসে আসুন'],
                ['Talk to our team about air tickets, visas, tours, Hajj or Umrah. Call, WhatsApp, email or visit our office on Box Culvert Road, Dhaka.',
                    'এয়ার টিকেট, ভিসা, ট্যুর, হজ বা উমরাহ নিয়ে আমাদের টিমের সাথে কথা বলুন। কল, হোয়াটসঅ্যাপ, ইমেইল করুন বা ঢাকার বক্স কালভার্ট রোডে আমাদের অফিসে আসুন।']],
            'gallery' => ['haram-courtyard.webp',
                ['Gallery: Moments from Our Journeys', 'গ্যালারি: আমাদের সফরের মুহূর্ত'],
                ['Photos from our tours, Hajj workshops, Umrah groups and gatherings.',
                    'আমাদের ট্যুর, হজ কর্মশালা, উমরাহ গ্রুপ ও আয়োজনের ছবি।']],
            'blog' => ['quran.webp',
                ['Travel Guides, Hajj & Umrah News', 'ভ্রমণ গাইড, হজ ও উমরাহ সংবাদ'],
                ['Travel tips, visa updates and step-by-step Hajj and Umrah guides from the Antarik Travel International team.',
                    'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল টিমের ভ্রমণ টিপস, ভিসার হালনাগাদ এবং ধাপে ধাপে হজ ও উমরাহ গাইড।']],
            'reviews' => ['green-dome.webp',
                ['Traveller & Pilgrim Reviews', 'ভ্রমণকারী ও হাজিদের রিভিউ'],
                ['What travellers and pilgrims say about their trips, Hajj and Umrah with Antarik Travel International.',
                    'আন্তরিক ট্রাভেল ইন্টারন্যাশনালের সাথে সফর, হজ ও উমরাহ নিয়ে ভ্রমণকারী ও হাজিদের মতামত।']],
        ];

        foreach ($pages as $key => [$photo, $title, $description]) {
            $this->fill(Page::where('system_key', $key)->first(), $photo, $title, $description);
        }

        $bySlug = [
            'air-tickets' => ['travel/airplane-sky.webp',
                ['Air Tickets: Domestic & International Fares', 'এয়ার টিকেট: অভ্যন্তরীণ ও আন্তর্জাতিক ভাড়া'],
                ['Request a fare quote from an IATA accredited agent in Dhaka: one-way, return and multi-city tickets on every major airline, with group and student fares.',
                    'ঢাকার আইএটিএ স্বীকৃত এজেন্টের কাছে ভাড়ার কোটেশন নিন: সব বড় এয়ারলাইনের ওয়ান-ওয়ে, রিটার্ন ও মাল্টি-সিটি টিকেট, গ্রুপ ও স্টুডেন্ট ফেয়ারসহ।']],
            'visa' => ['travel/passport-visa.webp',
                ['Visa Processing: Tourist, Business, Student & Work', 'ভিসা প্রসেসিং: ট্যুরিস্ট, বিজনেস, স্টুডেন্ট ও ওয়ার্ক'],
                ['Visa files for the Middle East, Asia, Europe and the UK, North America and Oceania: document checks, forms, appointments and honest advice.',
                    'মধ্যপ্রাচ্য, এশিয়া, ইউরোপ ও যুক্তরাজ্য, উত্তর আমেরিকা ও ওশেনিয়ার ভিসা ফাইল: কাগজপত্র যাচাই, ফরম, অ্যাপয়েন্টমেন্ট ও আন্তরিক পরামর্শ।']],
            'terms-and-conditions' => ['travel/airport.webp',
                ['Terms & Conditions', 'শর্তাবলী'],
                ['The terms for air tickets, visa processing, tour packages, Hajj and Umrah booked with Antarik Travel International.',
                    'আন্তরিক ট্রাভেল ইন্টারন্যাশনালের মাধ্যমে বুক করা এয়ার টিকেট, ভিসা প্রসেসিং, ট্যুর প্যাকেজ, হজ ও উমরাহর শর্তাবলী।']],
            'privacy-policy' => ['travel/airport.webp',
                ['Privacy Policy', 'গোপনীয়তা নীতি'],
                ['How Antarik Travel International collects, uses and protects your personal information and documents when you book a trip.',
                    'সফর বুক করার সময় আন্তরিক ট্রাভেল ইন্টারন্যাশনাল কীভাবে আপনার ব্যক্তিগত তথ্য ও কাগজপত্র সংগ্রহ, ব্যবহার ও সুরক্ষা করে।']],
            'hajj-pre-registration' => ['hajj-campaign.webp',
                ['Hajj Pre-registration', 'হজ প্রাক-নিবন্ধন'],
                ['Pre-register for the coming Hajj with Antarik Hajj Kafela: an NID or passport copy and an active mobile number are enough to start.',
                    'আন্তরিক হজ্জ কাফেলার সাথে আগামী হজের প্রাক-নিবন্ধন করুন: জাতীয় পরিচয়পত্র বা পাসপোর্টের কপি ও সক্রিয় মোবাইল নম্বরই যথেষ্ট।']],
        ];

        foreach ($bySlug as $slug => [$photo, $title, $description]) {
            $this->fill(Page::where('slug', $slug)->first(), $photo, $title, $description);
        }
    }

    private function services(): void
    {
        $services = [
            'hajj' => ['hajj-crowd.webp',
                ['Hajj Packages from Bangladesh', 'বাংলাদেশ থেকে হজ প্যাকেজ'],
                ['Hajj packages from Dhaka with hotels near the Haram, Bangladeshi meals, flights and scholars beside you. Compare VIP, shifting and premium options.',
                    'হারামের কাছে হোটেল, বাংলাদেশি খাবার, ফ্লাইট ও আলেমদের সান্নিধ্যসহ ঢাকা থেকে হজ প্যাকেজ। ভিআইপি, শিফটিং ও প্রিমিয়াম অপশন তুলনা করুন।']],
            'tours' => ['travel/travellers.webp',
                ['Tour Packages from Dhaka', 'ঢাকা থেকে ট্যুর প্যাকেজ'],
                ['Holiday packages from Dhaka to Thailand, Malaysia, Dubai, the Maldives, Türkiye, Europe, Japan and more, with flights, hotels, sightseeing and visa support.',
                    'ঢাকা থেকে থাইল্যান্ড, মালয়েশিয়া, দুবাই, মালদ্বীপ, তুরস্ক, ইউরোপ, জাপানসহ আরও অনেক গন্তব্যে হলিডে প্যাকেজ—ফ্লাইট, হোটেল, দর্শনীয় স্থান ও ভিসা সহায়তাসহ।']],
            'umrah' => ['haram-wide.webp',
                ['Umrah Packages from Bangladesh', 'বাংলাদেশ থেকে উমরাহ প্যাকেজ'],
                ['7 and 14-day Umrah packages from Dhaka, from five-star hotels near the Haram to budget stays, with visa, flights and scholar-led Ziyarah.',
                    'ঢাকা থেকে ৭ ও ১৪ দিনের উমরাহ প্যাকেজ—হারামের কাছে পাঁচ তারকা থেকে সাশ্রয়ী আবাসন, ভিসা, ফ্লাইট ও আলেমের নেতৃত্বে যিয়ারাহসহ।']],
        ];

        foreach ($services as $slug => [$photo, $title, $description]) {
            $this->fill(Service::where('slug', $slug)->first(), $photo, $title, $description);
        }
    }

    private function packages(): void
    {
        $packages = [
            'hajj-regular-package' => ['hajj-regular.webp',
                ['Hajj Regular Package', 'হজ রেগুলার প্যাকেজ'],
                ['VIP short-stay or 35–40 day shifting and permanent Hajj packages from Dhaka, with hotels near the Haram and three Bangladeshi meals daily.',
                    'ঢাকা থেকে ভিআইপি স্বল্পমেয়াদি অথবা ৩৫–৪০ দিনের শিফটিং ও পার্মানেন্ট হজ প্যাকেজ—হারামের কাছে হোটেল ও দিনে তিন বেলা বাংলাদেশি খাবারসহ।']],
            'hajj-premium-package' => ['hajj-premium.webp',
                ['Hajj Premium Package', 'হজ প্রিমিয়াম প্যাকেজ'],
                ['Permanent Hajj packages closer to the Haram, with buffet or Bangladeshi meals, star-rated hotels in Madinah and scholars throughout the journey.',
                    'হারামের আরও কাছে পার্মানেন্ট হজ প্যাকেজ—বুফে বা বাংলাদেশি খাবার, মদিনায় স্টার মানের হোটেল ও পুরো সফরে আলেমদের সান্নিধ্য।']],
            'umrah-package' => ['green-dome.webp',
                ['Umrah Package from Dhaka', 'ঢাকা থেকে উমরাহ প্যাকেজ'],
                ['7 and 14-day Umrah with hotels from five-star (0–100 m) to standard, Bangladeshi meals, visa, flights and scholar-led Ziyarah in Makkah and Madinah.',
                    '৭ ও ১৪ দিনের উমরাহ—পাঁচ তারকা (০–১০০ মিটার) থেকে স্ট্যান্ডার্ড হোটেল, বাংলাদেশি খাবার, ভিসা, ফ্লাইট এবং মক্কা ও মদিনায় আলেমের নেতৃত্বে যিয়ারাহ।']],
        ];

        foreach ($packages as $slug => [$photo, $title, $description]) {
            $this->fill(Package::where('slug', $slug)->first(), $photo, $title, $description);
        }

        foreach (TourSeeder::tours() as $tour) {
            $photo = 'travel/'.$tour['image'];
            $this->fill(Package::where('slug', TourSeeder::slug($tour))->first(), $photo, $tour['name'], $tour['summary'], $tour['alt']);
        }
    }

    /**
     * Posts keep their own title and excerpt for search; they get a share image.
     */
    private function posts(): void
    {
        $photos = [
            'what-is-umrah' => 'haram-wide.webp',
            'how-to-perform-umrah' => 'hajj-regular.webp',
            'important-information-for-hajj-pilgrims' => 'makkah-hotels.webp',
            'hajj-2028-registration' => 'madinah-golden.webp',
        ];

        foreach ($photos as $slug => $photo) {
            $this->fill(Post::where('slug', $slug)->first(), $photo);
        }
    }

    /**
     * @param  array{0: string, 1: string}|null  $title
     * @param  array{0: string, 1: string}|null  $description
     * @param  array{0: string, 1: string}|null  $alt  for photos not listed in PHOTOS
     */
    private function fill(?Model $model, string $photo, ?array $title = null, ?array $description = null, ?array $alt = null): void
    {
        if (! $model) {
            return;
        }

        $seo = $model->seo ?? [];

        if ($title && blank(array_filter($seo['title'] ?? []))) {
            $seo['title'] = ['en' => $title[0], 'bn' => $title[1]];
        }

        if ($description && blank(array_filter($seo['description'] ?? []))) {
            $seo['description'] = ['en' => $description[0], 'bn' => $description[1]];
        }

        if (empty($seo['image'])) {
            $seo['image'] = $this->share($photo, $alt)->id;
        }

        $model->forceFill(['seo' => $seo])->save();
    }

    /**
     * @param  string  $photo  a file in images/heroes, or a path such as travel/dubai.webp
     */
    private function share(string $photo, ?array $alt = null): Media
    {
        return $this->importImage(str_contains($photo, '/') ? $photo : "heroes/{$photo}", 'og', $alt ?? self::PHOTOS[$photo]);
    }
}
