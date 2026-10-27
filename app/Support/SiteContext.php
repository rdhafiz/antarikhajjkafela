<?php

namespace App\Support;

use App\Domain\Menus\MenuRepository;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Data shared by the public layout: menus, contact details, social links
 * and languages, formatted for display and for tel:/WhatsApp links.
 */
class SiteContext
{
    private const SOCIAL_LABELS = [
        'facebook' => 'Facebook',
        'messenger' => 'Messenger',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'twitter' => 'X (Twitter)',
        'telegram' => 'Telegram',
        'tiktok' => 'TikTok',
    ];

    public function __construct(
        private readonly MenuRepository $menus,
        private readonly Locales $locales,
    ) {}

    public function name(): string
    {
        return (string) setting_t('branding.site_name');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function menu(string $location): array
    {
        return $this->menus->tree($location);
    }

    /**
     * @return list<array{display: string, href: string}>
     */
    public function phones(): array
    {
        return collect([settings('contact.primary_phone'), settings('contact.secondary_phone')])
            ->filter()
            ->map(fn ($phone) => ['display' => $this->localDisplay($phone), 'href' => 'tel:'.$this->international($phone)])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        return array_values(array_filter([settings('contact.primary_email'), settings('contact.secondary_email')]));
    }

    public function whatsappUrl(?string $message = null): ?string
    {
        $number = settings('contact.primary_whatsapp');

        if (blank($number)) {
            return null;
        }

        $digits = ltrim($this->international($number), '+');

        return "https://wa.me/{$digits}".($message ? '?text='.rawurlencode($message) : '');
    }

    public function address(): ?string
    {
        return setting_t('contact.address');
    }

    public function mapEmbedUrl(): ?string
    {
        return settings('contact.google_map');
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function socials(): array
    {
        return collect(settings()->group('social'))
            ->filter()
            ->map(fn ($url, $key) => ['key' => $key, 'label' => self::SOCIAL_LABELS[$key] ?? ucfirst($key), 'url' => $url])
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, name: string, native: string, dir: string, active: bool, url: string}>
     */
    public function languages(): array
    {
        return collect($this->locales->available())
            ->map(fn ($locale) => [
                ...$locale,
                'active' => $locale['code'] === app()->getLocale(),
                'url' => route('locale.switch', $locale['code']),
            ])
            ->values()
            ->all();
    }

    /**
     * Bangladeshi numbers like 01711-381901 become +8801711381901.
     */
    /**
     * Bangladeshi numbers in their familiar local form ("01711-381901"); others as saved.
     */
    private function localDisplay(string $phone): string
    {
        try {
            $number = new PhoneNumber($phone, 'BD');

            return $number->isOfCountry('BD') ? $number->formatNational() : $number->formatInternational();
        } catch (Throwable) {
            return $phone;
        }
    }

    private function international(string $phone): string
    {
        try {
            return (new PhoneNumber($phone, 'BD'))->formatE164();
        } catch (Throwable) {
            // Fall back to the digit rules below.
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return match (true) {
            str_starts_with($phone, '+') => '+'.$digits,
            str_starts_with($digits, '880') => '+'.$digits,
            str_starts_with($digits, '0') && strlen($digits) === 11 => '+88'.$digits,
            default => '+'.$digits,
        };
    }
}
