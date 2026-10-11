import type { Component } from 'vue';
import { BriefcaseBusiness, FileText, GalleryHorizontalEnd, Images, Inbox, LayoutDashboard, ListTree, Megaphone, MessageSquareQuote, Newspaper, Package, Settings, ShieldCheck, Users } from 'lucide-vue-next';

export interface NavItem {
    label: string;
    to: string;
    icon: Component;
    permission?: string;
    /** Key of a counter in the app store shown as a badge. */
    badge?: 'inquiriesUnread';
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
            { label: 'Dashboard', to: '/admin', icon: LayoutDashboard, permission: 'dashboard.view' },
            { label: 'Inquiries', to: '/admin/inquiries', icon: Inbox, permission: 'inquiries.view', badge: 'inquiriesUnread' },
        ],
    },
    {
        label: 'Travel',
        items: [
            { label: 'Packages', to: '/admin/packages', icon: Package, permission: 'packages.view' },
            { label: 'Services', to: '/admin/services', icon: BriefcaseBusiness, permission: 'services.view' },
        ],
    },
    {
        label: 'Content',
        items: [
            { label: 'Pages', to: '/admin/pages', icon: FileText, permission: 'pages.view' },
            { label: 'Campaigns', to: '/admin/campaigns', icon: Megaphone, permission: 'campaigns.view' },
            { label: 'Blog & news', to: '/admin/blog', icon: Newspaper, permission: 'blog.view' },
            { label: 'Reviews', to: '/admin/reviews', icon: MessageSquareQuote, permission: 'reviews.view' },
            { label: 'Gallery', to: '/admin/gallery', icon: GalleryHorizontalEnd, permission: 'gallery.view' },
            { label: 'Menus', to: '/admin/menus', icon: ListTree, permission: 'menus.view' },
            { label: 'Media library', to: '/admin/media', icon: Images, permission: 'media.view' },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', to: '/admin/users', icon: Users, permission: 'users.view' },
            { label: 'Roles & permissions', to: '/admin/roles', icon: ShieldCheck, permission: 'roles.view' },
            { label: 'Settings', to: '/admin/settings', icon: Settings, permission: 'settings.view' },
        ],
    },
];
