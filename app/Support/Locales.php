<?php

namespace App\Support;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\File;

/**
 * Discovers the available languages from the JSON files in lang/.
 *
 * Adding a language only requires a new lang/{code}.json file that contains
 * the "locale.name" and "locale.native" keys; no code changes are needed.
 */
class Locales
{
    /** @var array<string, array{code: string, name: string, native: string, dir: string}>|null */
    private ?array $available = null;

    /**
     * The application's base language from config, captured before any
     * request changes the active locale (setLocale() rewrites app.locale).
     */
    private readonly string $base;

    public function __construct(private readonly Settings $settings)
    {
        $this->base = config('app.locale');
    }

    public function base(): string
    {
        return $this->base;
    }

    /**
     * @return array<string, array{code: string, name: string, native: string, dir: string}>
     */
    public function available(): array
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $locales = [];

        foreach (File::glob(lang_path('*.json')) as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            $strings = json_decode((string) file_get_contents($file), true) ?: [];

            $locales[$code] = [
                'code' => $code,
                'name' => $strings['locale.name'] ?? strtoupper($code),
                'native' => $strings['locale.native'] ?? strtoupper($code),
                'dir' => $strings['locale.dir'] ?? 'ltr',
            ];
        }

        // Keep the application's base language first, the rest alphabetically.
        $base = $this->base;
        uksort($locales, fn ($a, $b) => [$a !== $base, $a] <=> [$b !== $base, $b]);

        return $this->available = $locales;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->available());
    }

    public function isSupported(?string $code): bool
    {
        return $code !== null && array_key_exists($code, $this->available());
    }

    public function default(): string
    {
        $configured = $this->settings->get('localization.default_locale');

        return $this->isSupported($configured) ? $configured : $this->base;
    }
}
