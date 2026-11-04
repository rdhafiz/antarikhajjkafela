import type { Translations } from '@admin/types';

export type FieldType =
    | 'text'
    | 'textarea'
    | 'richtext'
    | 'image'
    | 'link'
    | 'select'
    | 'toggle'
    | 'number'
    | 'color'
    | 'icon'
    | 'youtube'
    | 'datetime'
    | 'repeater'
    | 'blocks';

export interface FieldDef {
    key: string;
    type: FieldType;
    label: string;
    hint?: string;
    placeholder?: string;
    required?: boolean;
    translatable?: boolean;
    max?: number;
    min?: number;
    rows?: number;
    usage?: string;
    options?: Record<string, string>;
    fields?: FieldDef[];
    default?: unknown;
    item_label?: string;
    show_if?: { field: string; values: (string | boolean)[] };
}

export interface ComponentDef {
    key: string;
    name: string;
    description: string;
    category: 'hero' | 'content' | 'conversion' | 'media' | 'dynamic';
    icon: string;
    repeatable: boolean;
    fields: FieldDef[];
    defaults: Record<string, unknown>;
    default_style: Record<string, unknown>;
}

export interface BuilderMeta {
    components: ComponentDef[];
    pickable: string[];
    style_fields: FieldDef[];
    icons: { name: string; svg: string }[];
    reserved_slugs: string[];
}

export interface SectionData {
    /** Client-side key (stable while editing, also for unsaved sections). */
    uid: string;
    id?: number;
    component: string;
    is_visible: boolean;
    content: Record<string, unknown>;
    style: Record<string, unknown>;
}

export interface PageSummary {
    id: number;
    type: 'default' | 'custom' | 'campaign';
    system_key: string | null;
    title: Translations;
    slug: string;
    url: string;
    status: 'draft' | 'published';
    state: 'draft' | 'published' | 'scheduled' | 'live' | 'expired';
    starts_at: string | null;
    expires_at: string | null;
    sections_count: number;
    /** Top section image or share image (list only). */
    cover_url?: string | null;
    updated_at: string | null;
}

export interface PageDetail extends PageSummary {
    has_listing: boolean;
    seo: { title: string | null; description: string | null; image: number | null; noindex: boolean; schema: string | null };
    focused_layout: boolean;
    tracking: { gtm_id?: string | null; facebook_pixel_id?: string | null };
    sections: Omit<SectionData, 'uid'>[];
}

/**
 * Deep copy of plain JSON data. Unlike structuredClone it also accepts Vue's
 * reactive proxies, which is what editor state usually is.
 */
export function deepClone<T>(value: T): T {
    return value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T);
}

let counter = 0;

export function uid(): string {
    counter += 1;
    return `s${Date.now().toString(36)}${counter}`;
}

/** Translated text for display in the admin (default language first). */
/** A section's own reference, which buttons jumping to the section point to (SectionStyle::newRef()). */
export function sectionRef(): string {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    return Array.from(crypto.getRandomValues(new Uint8Array(10)), (n) => chars[n % chars.length]).join('');
}

/** Sections a link can jump to (campaign pages), provided by the page editor. */
export interface LinkTarget {
    ref: string;
    label: string;
}

export const LINK_TARGETS = Symbol('link-targets');

/** The page's address on the site, e.g. /marketing/umrah-offer for a campaign (taken from the server's URL). */
export function pagePath(page: { url: string }): string {
    try {
        return new URL(page.url).pathname;
    } catch {
        return page.url;
    }
}

export function pickText(value: unknown, locale = 'en'): string {
    if (typeof value === 'string') return value;
    if (value && typeof value === 'object') {
        const record = value as Record<string, string | null>;
        return record[locale] || Object.values(record).find(Boolean) || '';
    }
    return '';
}

/** Whether a field should be shown given its sibling values. */
export function isFieldVisible(field: FieldDef, values: Record<string, unknown>): boolean {
    if (!field.show_if) return true;
    return field.show_if.values.includes(values[field.show_if.field] as string | boolean);
}

/** Empty value for a field (used for new repeater items). */
export function emptyValue(field: FieldDef): unknown {
    if (field.default !== undefined && field.default !== null) return deepClone(field.default);

    switch (field.type) {
        case 'text':
        case 'textarea':
        case 'richtext':
            return field.translatable ? {} : null;
        case 'link':
            return { label: {}, url: null };
        case 'toggle':
            return false;
        case 'repeater':
        case 'blocks':
            return [];
        default:
            return null;
    }
}

export function newRepeaterItem(fields: FieldDef[]): Record<string, unknown> {
    return Object.fromEntries([['_id', uid()], ...fields.map((f) => [f.key, emptyValue(f)])]);
}
