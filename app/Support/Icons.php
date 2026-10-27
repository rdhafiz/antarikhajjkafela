<?php

namespace App\Support;

/**
 * The website's inline SVG icon set. Used by <x-site.icon>, by page-builder
 * validation and by the admin icon picker, so all three always match.
 */
class Icons
{
    /** Stroke icons on a 24px grid. */
    public const STROKE = [
        'phone' => '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'map-pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'plane' => '<path d="M10.5 13.5 3 11l1.5-1.5 7.5 1L16.5 6a2.1 2.1 0 0 1 3 3L15 13.5l1 7.5-1.5 1.5-2.5-7.5L9 18v2.5L7.5 22 6 18l-4-1.5L3.5 15H6z"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M3 21h18M8 7h4M8 11h4M8 15h4"/>',
        'hotel' => '<path d="M3 21V8l9-5 9 5v13M3 21h18M9 21v-5h6v5M8 11h.01M12 11h.01M16 11h.01"/>',
        'bus' => '<rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 11h16M8 18v2M16 18v2M8 14.5h.01M16 14.5h.01"/>',
        'passport' => '<rect x="5" y="2.5" width="14" height="19" rx="2"/><circle cx="12" cy="10" r="3.5"/><path d="M9 17h6"/>',
        'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h18v-2a2 2 0 0 1 0-4 2 2 0 0 1 0-4V6H3z" transform="translate(0 -1)"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'shield' => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.6 7.5 9.5 4.3-.9 7.5-4.9 7.5-9.5V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
        'quote' => '<path d="M10 7H6a2 2 0 0 0-2 2v4h6V9M20 7h-4a2 2 0 0 0-2 2v4h6V9M10 13c0 3-2 4-4 4M20 13c0 3-2 4-4 4"/>',
        'play' => '<path d="M7 4.5v15l12-7.5z"/>',
        'pause' => '<path d="M8 5v14M16 5v14"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'home' => '<path d="m3 11 9-7 9 7M5 9.5V20h5v-6h4v6h5V9.5"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.2a4.3 4.3 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
        'medal' => '<circle cx="12" cy="15" r="5"/><path d="m8.5 11-3-8h4l2.5 6M15.5 11l3-8h-4L12 9"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
        'kaaba' => '<path d="M4 8.5 12 5l8 3.5v9L12 21l-8-3.5z"/><path d="M4 8.5 12 12l8-3.5M12 12v9M4 11.5l8 3.5 8-3.5"/>',
        'mosque' => '<path d="M5 21v-7a7 7 0 0 1 14 0v7M3 21h18M12 3v2M10 21v-4a2 2 0 0 1 4 0v4M2 21V11l1-2 1 2v10M20 21V11l1-2 1 2v10"/>',
        'moon' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a7 7 0 1 0 10.5 10.5z"/>',
        'hands' => '<path d="M8 13V6.5a1.5 1.5 0 0 1 3 0V12M11 11V4.5a1.5 1.5 0 0 1 3 0V11M14 11V6a1.5 1.5 0 0 1 3 0v8a7 7 0 0 1-7 7h-.5A6.5 6.5 0 0 1 4 16.4L3 13a1.5 1.5 0 0 1 2.8-1L8 15"/>',
        'utensils' => '<path d="M7 3v8a2 2 0 0 0 4 0V3M9 11v10M16 21V3c-2 0-3 2-3 5v6h3"/>',
        'stethoscope' => '<path d="M5 3v6a5 5 0 0 0 10 0V3M10 14v2a5 5 0 0 0 10 0v-1"/><circle cx="20" cy="13" r="2"/>',
        'headset' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3M4 15a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 2zM20 15a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2zM17 17c0 2-2 3-5 3"/>',
        'wallet' => '<path d="M4 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a1 1 0 0 1-1-1zM4 7l11-3v3M16 13.5h.01"/>',
        'document' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'upload' => '<path d="M12 15V4M7 9l5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path d="m21 16-5-5-9 9"/>',
        'images' => '<rect x="7" y="3" width="14" height="14" rx="2"/><path d="M3 7v12a2 2 0 0 0 2 2h12"/><path d="m21 13-4-4-7 7"/>',
        'link' => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2"/>',
        'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
        'expand' => '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
        'sparkles' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 3v4M21 5h-4M5 17v4M7 19H3"/>',
    ];

    /** Filled brand marks. */
    public const FILLED = [
        'whatsapp' => '<path d="M12 2.2A9.8 9.8 0 0 0 3.6 17l-1.4 4.9 5-1.3A9.8 9.8 0 1 0 12 2.2zm0 17.9a8.1 8.1 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.1 8.1 0 1 1 12 20.1zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4a.5.5 0 0 0 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 5 5 0 0 0 1 2.6 11.3 11.3 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6a2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .1-1.2c0-.1-.2-.2-.4-.3z"/>',
        'messenger' => '<path fill-rule="evenodd" d="M12 2.2c-5.5 0-9.8 4-9.8 9.4 0 2.8 1.2 5.3 3.2 7v3.2l3-1.7c1.1.3 2.3.5 3.6.5 5.5 0 9.8-4 9.8-9.4S17.5 2.2 12 2.2zm1 12.4-2.5-2.6-4.8 2.6 5.3-5.6 2.5 2.6 4.8-2.6z"/>',
        'facebook' => '<path d="M13.5 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3a21 21 0 0 0-2.3-.1c-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6V21z"/>',
        'instagram' => '<path d="M12 7.3a4.7 4.7 0 1 0 0 9.4 4.7 4.7 0 0 0 0-9.4zm0 7.7a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm6-7.9a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0zM21 8.2a5.4 5.4 0 0 0-1.5-3.8A5.4 5.4 0 0 0 15.8 3c-1.5-.1-6-.1-7.5 0a5.4 5.4 0 0 0-3.8 1.4A5.4 5.4 0 0 0 3 8.2c-.1 1.5-.1 6 0 7.5a5.4 5.4 0 0 0 1.5 3.8A5.4 5.4 0 0 0 8.2 21c1.5.1 6 .1 7.5 0a5.4 5.4 0 0 0 3.8-1.5 5.4 5.4 0 0 0 1.5-3.8c.1-1.5.1-6 0-7.5zm-2 9.1a3 3 0 0 1-1.7 1.7c-1.2.5-4 .4-5.3.4s-4.1.1-5.3-.4A3 3 0 0 1 5 17.3c-.5-1.2-.4-4-.4-5.3s-.1-4.1.4-5.3A3 3 0 0 1 6.7 5c1.2-.5 4-.4 5.3-.4s4.1-.1 5.3.4A3 3 0 0 1 19 6.7c.5 1.2.4 4 .4 5.3s.1 4.1-.4 5.3z"/>',
        'youtube' => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3z"/>',
        'linkedin' => '<path d="M6.9 21H3.4V9.3h3.5zM5.2 7.7a2 2 0 1 1 0-4.1 2 2 0 0 1 0 4.1zM21 21h-3.5v-5.7c0-1.4 0-3.1-1.9-3.1s-2.2 1.5-2.2 3V21H9.9V9.3h3.4v1.6a3.7 3.7 0 0 1 3.3-1.8c3.5 0 4.2 2.3 4.2 5.4z"/>',
        'twitter' => '<path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.3l4.4 5.9zm-1.1 16.2h1.7L7.4 4.7H5.6z"/>',
        'telegram' => '<path d="M21.4 4.3 2.9 11.4c-1.3.5-1.2 1.2-.2 1.5l4.7 1.5 1.8 5.6c.2.6.4.8.8.8s.6-.2.9-.5l2.3-2.2 4.7 3.5c.9.5 1.5.2 1.7-.8l3.1-14.6c.3-1.3-.5-1.9-1.3-1.5zM9.6 14.1l8.6-7.2-6.9 8.1-.3 3.2z"/>',
        'tiktok' => '<path d="M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-1.8-2.5V9.8a5.7 5.7 0 1 0 4.9 5.6V9.1a7.3 7.3 0 0 0 4.3 1.4V7.4a4.3 4.3 0 0 1-3.2-1.6z"/>',
    ];

    /** Icons offered in the page builder (decorative content icons). */
    public const CONTENT = [
        'kaaba', 'mosque', 'moon', 'hands', 'book', 'shield', 'medal', 'heart', 'star', 'sparkles', 'compass',
        'plane', 'ticket', 'passport', 'hotel', 'building', 'bus', 'utensils', 'stethoscope', 'headset',
        'users', 'user', 'calendar', 'clock', 'wallet', 'document', 'check-circle', 'map-pin', 'phone', 'mail', 'globe',
    ];

    public static function exists(string $name): bool
    {
        return isset(self::STROKE[$name]) || isset(self::FILLED[$name]);
    }

    public static function svg(string $name, string $attributes = 'class="size-5" aria-hidden="true"'): string
    {
        if (isset(self::FILLED[$name])) {
            return '<svg '.$attributes.' viewBox="0 0 24 24" fill="currentColor">'.self::FILLED[$name].'</svg>';
        }

        return '<svg '.$attributes.' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'
            .(self::STROKE[$name] ?? '').'</svg>';
    }

    /**
     * @return list<array{name: string, svg: string}>
     */
    public static function contentIcons(): array
    {
        return array_map(fn ($name) => ['name' => $name, 'svg' => self::svg($name, 'aria-hidden="true"')], self::CONTENT);
    }
}
