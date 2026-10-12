import type { Component } from 'vue';
import { BriefcaseBusiness, CalendarCheck, FileText, GalleryHorizontalEnd, Images, Inbox, LayoutDashboard, ListTree, Megaphone, MessageSquareQuote, Newspaper, Package, Settings, ShieldCheck, Users } from 'lucide-vue-next';

export interface NavItem {
    label: string;
    to: string;
    icon: Component;
    /** Icon colour: a hue that hints at what the module is for. */
    tone: string;
    permission?: string;
    /** Key of a counter in the app store shown as a badge. */
    badge?: 'inquiriesUnread' | 'bookingsUnread';
}

export interface NavSection {
    label?: string;
    items: NavItem[];
}

/**
 * Sidebar navigation. Items the current user has no permission for are hidden.
 * Modules are added here as they are built.
 */
export const navigation: NavSection[] = [
    {
        items: [
            { label: 'Dashboard', to: '/admin', icon: LayoutDashboard, tone: '#818cf8', permission: 'dashboard.view' },
            { label: 'Bookings', to: '/admin/bookings', icon: CalendarCheck, tone: '#34d399', permission: 'bookings.view', badge: 'bookingsUnread' },
            { label: 'Inquiries', to: '/admin/inquiries', icon: Inbox, tone: '#38bdf8', permission: 'inquiries.view', badge: 'inquiriesUnread' },
        ],
    },
    {
        label: 'Travel',
        items: [
            { label: 'Packages', to: '/admin/packages', icon: Package, tone: '#fbbf24', permission: 'packages.view' },
            { label: 'Services', to: '/admin/services', icon: BriefcaseBusiness, tone: '#a78bfa', permission: 'services.view' },
        ],
    },
    {
        label: 'Content',
        items: [
            { label: 'Pages', to: '/admin/pages', icon: FileText, tone: '#60a5fa', permission: 'pages.view' },
            { label: 'Campaigns', to: '/admin/campaigns', icon: Megaphone, tone: '#fb7185', permission: 'campaigns.view' },
            { label: 'Blog & news', to: '/admin/blog', icon: Newspaper, tone: '#fb923c', permission: 'blog.view' },
            { label: 'Reviews', to: '/admin/reviews', icon: MessageSquareQuote, tone: '#f472b6', permission: 'reviews.view' },
            { label: 'Gallery', to: '/admin/gallery', icon: GalleryHorizontalEnd, tone: '#2dd4bf', permission: 'gallery.view' },
            { label: 'Menus', to: '/admin/menus', icon: ListTree, tone: '#a3e635', permission: 'menus.view' },
            { label: 'Media library', to: '/admin/media', icon: Images, tone: '#22d3ee', permission: 'media.view' },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', to: '/admin/users', icon: Users, tone: '#93c5fd', permission: 'users.view' },
            { label: 'Roles & permissions', to: '/admin/roles', icon: ShieldCheck, tone: '#4ade80', permission: 'roles.view' },
            { label: 'Settings', to: '/admin/settings', icon: Settings, tone: '#cbd5e1', permission: 'settings.view' },
        ],
    },
];
