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
 * from the Unsplash-licensed photos in images/heroes, see docs/content/image-credits.md)
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
    ];

    public function run(Settings $settings): void
    {
        $this->pages();
        $this->services();
        $this->packages();
        $this->posts();

        // Default share image for pages without their own.
        if (blank($settings->get('seo.og_image'))) {
            $settings->set('seo', ['og_image' => $this->share('home-haram.webp')->id]);
        }
    }

    private function pages(): void
    {
        $pages = [
            'home' => ['home-haram.webp',
                ['Hajj & Umrah Packages from Bangladesh', 'বাংলাদেশ থেকে হজ ও উমরাহ প্যাকেজ'],
                ['Scholar-guided Hajj and Umrah packages from Dhaka since 2016, with hotels near the Haram, flights, visas and Bangladeshi meals. Book or call our team.',
                    '২০১৬ সাল থেকে ঢাকা থেকে আলেমদের তত্ত্বাবধানে হজ ও উমরাহ প্যাকেজ—হারামের কাছে হোটেল, ফ্লাইট, ভিসা ও বাংলাদেশি খাবারসহ। বুক করুন বা আমাদের টিমকে কল করুন।']],
            'about' => ['madinah-golden.webp',
                ['About Us: Our Story, Team & Mission', 'আমাদের সম্পর্কে: গল্প, টিম ও লক্ষ্য'],
                ['Since 2016 Antarik Hajj Kafela has guided pilgrims from Bangladesh with comfort, transparent dealings and experienced scholars. Meet our team and mission.',
                    '২০১৬ সাল থেকে আন্তরিক হজ্জ কাফেলা আরাম, স্বচ্ছ লেনদেন ও অভিজ্ঞ আলেমদের সাথে বাংলাদেশের হাজিদের সেবা দিয়ে আসছে। আমাদের টিম ও লক্ষ্য জানুন।']],
            'services' => ['makkah-hotels.webp',
                ['Hajj & Umrah Services', 'হজ ও উমরাহ সেবাসমূহ'],
                ['Umrah visa processing, air tickets, hotels near the Haram, transport, Hajj pre-registration and scholar-led workshops, arranged by one dedicated team.',
                    'উমরাহ ভিসা, এয়ার টিকেট, হারামের কাছে হোটেল, পরিবহন, হজ প্রাক-নিবন্ধন ও আলেমদের কর্মশালা—সবকিছু একটি নিবেদিত টিমের মাধ্যমে।']],
            'contact' => ['madinah-umbrellas.webp',
                ['Contact Us: Call, WhatsApp or Visit', 'যোগাযোগ: কল, হোয়াটসঅ্যাপ বা অফিসে আসুন'],
                ['Talk to our team about Hajj and Umrah packages, pre-registration or visas. Call, WhatsApp, email or visit our office on Box Culvert Road, Dhaka.',
                    'হজ ও উমরাহ প্যাকেজ, প্রাক-নিবন্ধন বা ভিসা নিয়ে আমাদের টিমের সাথে কথা বলুন। কল, হোয়াটসঅ্যাপ, ইমেইল করুন বা ঢাকার বক্স কালভার্ট রোডে আমাদের অফিসে আসুন।']],
            'gallery' => ['haram-courtyard.webp',
                ['Gallery: Hajj & Umrah Moments', 'গ্যালারি: হজ ও উমরাহর মুহূর্ত'],
                ['Photos from our Hajj workshops, Umrah groups and gatherings, as we prepare pilgrims from Bangladesh for their journey.',
                    'আমাদের হজ কর্মশালা, উমরাহ গ্রুপ ও আয়োজনের ছবি—বাংলাদেশের হাজিদের সফরের প্রস্তুতির মুহূর্তগুলো।']],
            'blog' => ['quran.webp',
                ['Hajj & Umrah Guides and News', 'হজ ও উমরাহ গাইড ও সংবাদ'],
                ['Step-by-step Umrah and Hajj guides, payment advice and news for pilgrims from Bangladesh, written by the Antarik Hajj Kafela team.',
                    'বাংলাদেশের হাজিদের জন্য ধাপে ধাপে উমরাহ ও হজ গাইড, পেমেন্ট পরামর্শ ও সংবাদ—আন্তরিক হজ্জ কাফেলা টিমের লেখা।']],
            'reviews' => ['green-dome.webp',
                ['Pilgrim Reviews', 'হাজিদের রিভিউ'],
                ['What pilgrims say about travelling for Hajj and Umrah with Antarik Hajj Kafela.',
                    'আন্তরিক হজ্জ কাফেলার সাথে হজ ও উমরাহ সফর নিয়ে হাজিদের মতামত।']],
        ];

        foreach ($pages as $key => [$photo, $title, $description]) {
            $this->fill(Page::where('system_key', $key)->first(), $photo, $title, $description);
        }

        $bySlug = [
            'terms-and-conditions' => ['nabawi-colonnade.webp',
                ['Terms & Conditions', 'শর্তাবলী'],
                ['The terms for booking, payments and travel on Hajj and Umrah packages with Antarik Hajj Kafela.',
                    'আন্তরিক হজ্জ কাফেলার হজ ও উমরাহ প্যাকেজের বুকিং, পেমেন্ট ও সফরের শর্তাবলী।']],
            'privacy-policy' => ['nabawi-colonnade.webp',
                ['Privacy Policy', 'গোপনীয়তা নীতি'],
                ['How Antarik Hajj Kafela collects, uses and protects your personal information and documents when you book Hajj or Umrah.',
                    'হজ বা উমরাহ বুকিংয়ের সময় আন্তরিক হজ্জ কাফেলা কীভাবে আপনার ব্যক্তিগত তথ্য ও কাগজপত্র সংগ্রহ, ব্যবহার ও সুরক্ষা করে।']],
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
     */
    private function fill(?Model $model, string $photo, ?array $title = null, ?array $description = null): void
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
            $seo['image'] = $this->share($photo)->id;
        }

        $model->forceFill(['seo' => $seo])->save();
    }

    private function share(string $photo): Media
    {
        return $this->importImage("heroes/{$photo}", 'og', self::PHOTOS[$photo]);
    }
}
