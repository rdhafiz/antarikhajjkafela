export type Translations = Record<string, string | null>;

export interface Locale {
    code: string;
    name: string;
    native: string;
    dir: 'ltr' | 'rtl';
}

export interface CurrentUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_super_admin: boolean;
    roles: string[];
    permissions: string[];
    last_login_at: string | null;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    roles?: string[];
    last_login_at: string | null;
    created_at: string | null;
}

export interface Role {
    id: number;
    name: string;
    label: string;
    is_super_admin: boolean;
    is_system: boolean;
    permissions?: string[];
    users_count?: number;
}

export interface PermissionModule {
    key: string;
    label: string;
    actions: string[];
}

export interface MediaVariant {
    url: string;
    width: number;
    height: number;
    size: number;
}

export interface Media {
    id: number;
    uuid: string;
    usage: string;
    original_name: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    alt: Translations;
    url: string | null;
    thumb_url: string | null;
    variants: Record<string, MediaVariant>;
    optimized_size: number;
    created_at: string | null;
}

export interface MediaUsageVariant {
    name: string;
    width: number | null;
    height: number | null;
    fit: 'scale' | 'cover';
    quality: number;
}

export interface MediaUsage {
    key: string;
    label: string;
    variants: MediaUsageVariant[];
}

export interface AdminTheme {
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    sidebar_bg: string;
    sidebar_text: string;
    sidebar_accent: string;
    sidebar_logo_bg: string;
    header_bg: string;
    header_text: string;
}

export interface Bootstrap {
    app: { name: string; url: string; logo: string | null; icon: string | null };
    locales: Locale[];
    default_locale: string;
    currency: string;
    admin_theme: AdminTheme;
    media_mode: 'automatic' | 'manual';
}
