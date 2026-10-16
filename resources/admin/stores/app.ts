import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '@admin/lib/http';
import type { AdminTheme, Bootstrap, CurrentUser, Locale } from '@admin/types';

const themeVariables: Record<keyof AdminTheme, string> = {
    primary_color: '--admin-primary',
    secondary_color: '--admin-secondary',
    accent_color: '--admin-accent',
    sidebar_bg: '--admin-sidebar-bg',
    sidebar_text: '--admin-sidebar-text',
    sidebar_accent: '--admin-sidebar-accent',
    sidebar_logo_bg: '--admin-sidebar-logo-bg',
    header_bg: '--admin-header-bg',
    header_text: '--admin-header-text',
};

export const useAppStore = defineStore('app', () => {
    const loaded = ref(false);
    const name = ref('Admin');
    const siteUrl = ref('/');
    const logo = ref<string | null>(null);
    const icon = ref<string | null>(null);
    const locales = ref<Locale[]>([]);
    const defaultLocale = ref('en');
    const currency = ref('BDT');
    const mediaMode = ref<'automatic' | 'manual'>('automatic');
    const inquiriesUnread = ref(0);
    const bookingsUnread = ref(0);

    /**
     * One request for everything the shell needs. Returns the signed-in
     * user it carries, or null when nobody is: /bootstrap needs a session,
     * so a rejection here is the answer to "is anyone signed in?".
     */
    async function load(force = false): Promise<CurrentUser | null> {
        if (loaded.value && !force) return null;

        let data: Bootstrap;

        try {
            ({ data } = await http.get<Bootstrap>('/bootstrap'));
        } catch {
            return null;
        }

        name.value = data.app.name;
        siteUrl.value = data.app.url;
        logo.value = data.app.logo;
        icon.value = data.app.icon;
        locales.value = data.locales;
        defaultLocale.value = data.default_locale;
        currency.value = data.currency;
        mediaMode.value = data.media_mode;
        inquiriesUnread.value = data.counts?.inquiries_unread ?? 0;
        bookingsUnread.value = data.counts?.bookings_unread ?? 0;
        applyTheme(data.admin_theme);

        loaded.value = true;

        return data.user;
    }

    /** Apply admin theme colors live (e.g. right after saving settings). */
    function applyTheme(theme: Partial<AdminTheme>): void {
        const root = document.documentElement.style;

        for (const [key, variable] of Object.entries(themeVariables)) {
            const value = theme[key as keyof AdminTheme];
            if (value) root.setProperty(variable, value);
        }
    }

    return { loaded, name, siteUrl, logo, icon, locales, defaultLocale, currency, mediaMode, inquiriesUnread, bookingsUnread, load, applyTheme };
});
