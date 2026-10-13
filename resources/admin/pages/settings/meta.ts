import type { Component } from 'vue';
import { Activity, BadgeCheck, CreditCard, Image as ImageIcon, Languages, Megaphone, Paintbrush, Palette, Phone, Search, Settings2, Share2 } from 'lucide-vue-next';

/** Icon per settings group, by the `icon` name in config/settings.php. */
const icons: Record<string, Component> = {
    badge: BadgeCheck,
    palette: Palette,
    languages: Languages,
    phone: Phone,
    share: Share2,
    search: Search,
    activity: Activity,
    megaphone: Megaphone,
    paintbrush: Paintbrush,
    image: ImageIcon,
    'credit-card': CreditCard,
};

/** A colour per settings group, by what it is about. */
const tones: Record<string, string> = {
    badge: '#fbbf24',
    palette: '#f472b6',
    languages: '#38bdf8',
    phone: '#34d399',
    share: '#60a5fa',
    search: '#a78bfa',
    activity: '#fb7185',
    megaphone: '#fb923c',
    paintbrush: '#2dd4bf',
    image: '#22d3ee',
    'credit-card': '#a3e635',
};

export const groupIcon = (icon: string | undefined): Component => icons[icon ?? ''] ?? Settings2;
export const groupTone = (icon: string | undefined): string => tones[icon ?? ''] ?? '#818cf8';

/** Black or white text, whichever reads better on a background colour. */
export function readableOn(hex: string): string {
    const match = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
    if (!match) return '#ffffff';
    const n = parseInt(match[1], 16);
    const channel = (c: number) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };
    const luminance = 0.2126 * channel((n >> 16) & 255) + 0.7152 * channel((n >> 8) & 255) + 0.0722 * channel(n & 255);
    // Pick the side with the higher contrast ratio.
    return (luminance + 0.05) / 0.05 > 1.05 / (luminance + 0.05) ? '#0b1220' : '#ffffff';
}
