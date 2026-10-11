<?php

namespace App\PageBuilder;

use App\Models\Media;
use Illuminate\Support\Collection;

/**
 * Per-section appearance: background (preset or custom color), optional
 * background image with a color overlay, text color, spacing and anchor.
 * Every component placement can be styled independently.
 */
class SectionStyle
{
    public const BACKGROUNDS = [
        'default' => 'Component default',
        'sand' => 'Sand',
        'white' => 'White',
        'sand-deep' => 'Deep sand',
        'navy' => 'Navy',
        'navy-deep' => 'Deep navy',
        'gold-soft' => 'Soft gold',
        'custom' => 'Custom color',
    ];

    public const SPACINGS = ['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'];

    private const DARK_PRESETS = ['navy', 'navy-deep'];

    private const PRESET_CLASSES = [
        'sand' => 'bg-sand-50',
        'white' => 'bg-white',
        'sand-deep' => 'bg-sand-100',
        'navy' => 'bg-brand',
        'navy-deep' => 'bg-brand-950',
        'gold-soft' => 'bg-gold-soft',
    ];

    private const SPACING_CLASSES = [
        'none' => '',
        'sm' => 'py-12 sm:py-14',
        'md' => 'py-16 sm:py-20',
        'lg' => 'py-20 sm:py-24 lg:py-28',
    ];

    /**
     * Admin form fields for the style tab.
     *
     * @return list<Field>
     */
    public static function fields(): array
    {
        return [
            Field::select('background', 'Background', self::BACKGROUNDS),
            Field::color('background_color', 'Background color')->showIf('background', ['custom']),
            Field::image('background_image', 'Background image', 'hero')->hint('Shown behind the section, under the overlay color.'),
            Field::color('overlay_color', 'Overlay color')->hint('Tints the background image. Leave empty for a dark navy overlay.'),
            Field::number('overlay_opacity', 'Overlay strength (%)', 0, 95)->default(70),
            Field::color('text_color', 'Text color')->hint('Leave empty to choose automatically from the background.'),
            Field::select('spacing', 'Vertical spacing', self::SPACINGS)->default('lg'),
            Field::text('anchor', 'Anchor ID')->notTranslatable()->max(40)->hint('Lets menus link to this section, e.g. #packages.'),
        ];
    }

    /**
     * Wrapper attributes for rendering a section.
     *
     * @param  array<string, mixed>  $style  stored style
     * @param  array<string, mixed>  $defaults  component default style
     * @param  Collection<int, Media>  $media
     * @return array{class: string, style: string, tone: string, image: Media|null, overlay: string|null, id: string|null}
     */
    public static function resolve(array $style, array $defaults, Collection $media): array
    {
        $style = array_merge($defaults, array_filter($style, fn ($v) => $v !== null && $v !== '' && $v !== 'default'));

        $background = $style['background'] ?? 'sand';
        $image = ! empty($style['background_image']) ? $media->get((int) $style['background_image']) : null;
        $customColor = $background === 'custom' ? ($style['background_color'] ?? null) : null;
        $textColor = $style['text_color'] ?? null;

        $tone = match (true) {
            $image !== null => self::isLight($style['overlay_color'] ?? null) && (int) ($style['overlay_opacity'] ?? 70) >= 60 ? 'light' : 'dark',
            $customColor !== null => self::isLight($customColor) ? 'light' : 'dark',
            default => in_array($background, self::DARK_PRESETS, true) ? 'dark' : 'light',
        };

        if ($textColor) {
            $tone = self::isLight($textColor) ? 'dark' : 'light';
        }

        $css = [];
        if ($customColor) {
            $css[] = "background-color: {$customColor}";
        }
        if ($textColor) {
            $css[] = "--sec-heading: {$textColor}; --sec-text: {$textColor}; --sec-muted: {$textColor}";
        }

        $overlay = null;
        if ($image) {
            $color = $style['overlay_color'] ?? '#06182e';
            $opacity = max(0, min(95, (int) ($style['overlay_opacity'] ?? 70))) / 100;
            $overlay = "background-color: {$color}; opacity: {$opacity}";
        }

        return [
            'class' => trim(implode(' ', [
                $customColor || $image ? '' : (self::PRESET_CLASSES[$background] ?? 'bg-sand-50'),
                self::SPACING_CLASSES[$style['spacing'] ?? 'lg'] ?? '',
                $tone === 'dark' ? 'section-dark' : 'section-light',
            ])),
            'style' => implode('; ', $css),
            'tone' => $tone,
            'image' => $image,
            'overlay' => $overlay,
            'id' => filled($style['anchor'] ?? null) ? str($style['anchor'])->slug()->toString() : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $style
     * @return array<string, mixed>
     */
    public static function sanitize(?array $style): array
    {
        $style ??= [];

        return [
            'background' => array_key_exists($style['background'] ?? '', self::BACKGROUNDS) ? $style['background'] : 'default',
            'background_color' => self::hex($style['background_color'] ?? null),
            'background_image' => ! empty($style['background_image']) ? (int) $style['background_image'] : null,
            'overlay_color' => self::hex($style['overlay_color'] ?? null),
            'overlay_opacity' => isset($style['overlay_opacity']) ? max(0, min(95, (int) $style['overlay_opacity'])) : 70,
            'text_color' => self::hex($style['text_color'] ?? null),
            'spacing' => array_key_exists($style['spacing'] ?? '', self::SPACINGS) ? $style['spacing'] : null,
            'anchor' => filled($style['anchor'] ?? null) ? str($style['anchor'])->slug()->limit(40, '')->toString() : null,
        ];
    }

    private static function hex(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    /**
     * Relative luminance check for automatic text contrast.
     */
    private static function isLight(?string $hex): bool
    {
        if (! $hex || ! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return false;
        }

        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, [$m[1], $m[2], $m[3]]);
        $linear = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return (0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b)) > 0.4;
    }
}
