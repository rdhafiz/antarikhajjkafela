<?php

namespace Database\Seeders;

use App\Domain\Travel\PackageEditor;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\Concerns\ImportsSeedImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The Tours service with sample tour packages (database/seeders/content/tours.php)
 * and their photos. Only runs when the service doesn't exist yet (also when
 * it is in the trash), so the admin's own tours are never touched.
 */
class TourSeeder extends Seeder
{
    use ImportsSeedImages;

    public function run(PackageEditor $editor): void
    {
        if (Service::everHad(['tours'])) {
            return;
        }

        $service = Service::create([
            'name' => $this->t('Tour Packages', 'ট্যুর প্যাকেজ'),
            'slug' => 'tours',
            'travel_date' => 'month_year',
            'icon' => 'globe',
            'sort' => 2,
            'headline' => $this->t('See the world,', 'বিশ্ব ঘুরে দেখুন,'),
            'highlight' => $this->t('we handle the rest.', 'বাকিটা আমাদের দায়িত্ব।'),
            'summary' => $this->t(
                'Holiday packages from Dhaka with flights, hotels, transfers, sightseeing and visa support, from Asia\'s favourites to Europe and the great Islamic cities.',
                'ফ্লাইট, হোটেল, ট্রান্সফার, দর্শনীয় স্থান ও ভিসা সহায়তাসহ ঢাকা থেকে হলিডে প্যাকেজ—এশিয়ার জনপ্রিয় গন্তব্য থেকে ইউরোপ ও ইসলামি ঐতিহ্যের শহর।'
            ),
            'description' => $this->t(
                "Every tour starts and ends in Dhaka. We book the flights, hotels and transfers, arrange the visa and send you a day-by-day plan before you leave, with halal food options noted for each city.\n\nTravelling as a family, a couple or a company group? Ask us to change the dates, hotels or length of any tour, or to plan one around your budget.",
                "প্রতিটি ট্যুর ঢাকা থেকে শুরু ও ঢাকায় শেষ। ফ্লাইট, হোটেল ও ট্রান্সফার আমরা বুক করি, ভিসার ব্যবস্থা করি এবং যাত্রার আগে প্রতিদিনের পরিকল্পনা পাঠাই—প্রতিটি শহরের হালাল খাবারের তথ্যসহ।\n\nপরিবার, দম্পতি বা অফিস গ্রুপ হিসেবে যাচ্ছেন? যেকোনো ট্যুরের তারিখ, হোটেল বা দিনের সংখ্যা বদলাতে, অথবা আপনার বাজেট অনুযায়ী নতুন ট্যুর সাজাতে আমাদের বলুন।"
            ),
            'image_id' => $this->importImage('travel/travellers.webp', 'banner', ['A family walking to a small plane on a sunny airstrip', 'রোদেলা এয়ারস্ট্রিপে ছোট বিমানের দিকে হেঁটে যাওয়া একটি পরিবার'])->id,
        ]);

        $user = User::role(config('access.super_admin_role'))->first() ?? User::first() ?? new User(['id' => null]);

        foreach ($this->tours() as $tour) {
            $editor->save(new Package, $this->package($service, $tour), $user);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function tours(): array
    {
        return require __DIR__.'/content/tours.php';
    }

    public static function slug(array $tour): string
    {
        return Str::slug($tour['name'][0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function package(Service $service, array $tour): array
    {
        $nights = $tour['days'] - 1;
        $hotels = array_map(fn ($h) => [
            'heading' => $this->t($h[0], $h[1]),
            'subheading' => $this->t($h[2], $h[3]),
            'description' => [],
        ], $tour['hotels']);

        return [
            'service_id' => $service->id,
            'name' => $this->pair($tour['name']),
            'summary' => $this->pair($tour['summary']),
            'banner_id' => $this->importImage('travel/'.$tour['image'], 'banner', $tour['alt'])->id,
            'duration_days' => $tour['days'],
            'status' => Package::STATUS_PUBLISHED,
            'is_featured' => $tour['featured'],
            'show_price' => true,
            'advance_type' => 'none',
            'flights' => [[
                'airline' => $this->pair($tour['airline']),
                'schedule' => $this->t('Departures every week, or on dates you choose', 'প্রতি সপ্তাহে যাত্রা, অথবা আপনার পছন্দের তারিখে'),
                'description' => $this->pair($tour['flight']),
            ]],
            'sections' => [
                $this->listSection('Itinerary', 'ভ্রমণসূচি', "{$tour['days']} days, {$nights} nights", $this->bnDigits($tour['days']).' দিন, '.$this->bnDigits($nights).' রাত', 'bullet', $tour['itinerary']),
                $this->listSection('Included', 'অন্তর্ভুক্ত', 'What the price includes', 'মূল্যে যা অন্তর্ভুক্ত', 'check', [
                    ['Return economy air tickets from Dhaka', 'ঢাকা থেকে রিটার্ন ইকোনমি এয়ার টিকেট'],
                    ['Hotels on twin sharing with daily breakfast', 'টুইন শেয়ারিংয়ে হোটেল ও প্রতিদিনের নাশতা'],
                    ['Airport transfers and sightseeing by private air-conditioned vehicle', 'প্রাইভেট শীতাতপ নিয়ন্ত্রিত গাড়িতে এয়ারপোর্ট ট্রান্সফার ও দর্শনীয় স্থান ভ্রমণ'],
                    ['Entry tickets for the visits in the itinerary', 'ভ্রমণসূচির দর্শনীয় স্থানের প্রবেশ টিকেট'],
                    ['Visa processing support and a day-by-day travel plan', 'ভিসা প্রসেসিং সহায়তা ও প্রতিদিনের ভ্রমণ পরিকল্পনা'],
                    ['Our Dhaka team on WhatsApp throughout the trip', 'পুরো সফরে হোয়াটসঅ্যাপে আমাদের ঢাকা টিম'],
                ]),
                $this->listSection('Not included', 'অন্তর্ভুক্ত নয়', 'Not included in the price', 'মূল্যে অন্তর্ভুক্ত নয়', 'bullet', [
                    ['Embassy visa fees, unless stated', 'উল্লেখ না থাকলে দূতাবাসের ভিসা ফি'],
                    ['Lunch and dinner, unless stated', 'উল্লেখ না থাকলে দুপুর ও রাতের খাবার'],
                    ['Optional activities, tips and personal expenses', 'ঐচ্ছিক কার্যক্রম, টিপস ও ব্যক্তিগত খরচ'],
                    ['Travel insurance (available on request)', 'ভ্রমণ বিমা (চাইলে ব্যবস্থা করা হয়)'],
                ]),
            ],
            'types' => [[
                'code' => $tour['code'].'-STD',
                'name' => $this->t('Standard tour', 'স্ট্যান্ডার্ড ট্যুর'),
                'short_description' => $this->t('Price per person. Children\'s prices on request.', 'জনপ্রতি মূল্য। শিশুদের মূল্য জানতে যোগাযোগ করুন।'),
                'prices' => [
                    ['title' => $this->t('Twin or triple sharing', 'টুইন বা ট্রিপল শেয়ারিং'), 'short_description' => $this->t('Two or three adults in a room.', 'এক রুমে দুই বা তিনজন প্রাপ্তবয়স্ক।'), 'price' => $tour['price'], 'unit' => 'person', 'hotels' => $hotels],
                    ['title' => $this->t('Single room', 'সিঙ্গেল রুম'), 'short_description' => $this->t('One adult in a room.', 'এক রুমে একজন প্রাপ্তবয়স্ক।'), 'price' => $tour['single'], 'unit' => 'person', 'hotels' => $hotels],
                ],
            ]],
        ];
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
            'blocks' => [['type' => 'list', 'style' => $style, 'items' => array_map(fn ($i) => $this->pair($i), $items)]],
        ];
    }

    private function bnDigits(int $number): string
    {
        return strtr((string) $number, ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯']);
    }

    /**
     * @param  array{0: string, 1: string}  $pair
     * @return array<string, string>
     */
    private function pair(array $pair): array
    {
        return $this->t($pair[0], $pair[1]);
    }

    /**
     * @return array<string, string>
     */
    private function t(string $en, string $bn): array
    {
        return ['en' => $en, 'bn' => $bn];
    }
}
