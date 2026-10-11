import type { Component } from 'vue';
import { Images, LayoutDashboard, ShieldCheck, Users } from 'lucide-vue-next';

export interface NavItem {
    label: string;
    to: string;
    icon: Component;
    permission?: string;
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
        items: [{ label: 'Dashboard', to: '/admin', icon: LayoutDashboard, permission: 'dashboard.view' }],
    },
    {
        label: 'Content',
        items: [{ label: 'Media library', to: '/admin/media', icon: Images, permission: 'media.view' }],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', to: '/admin/users', icon: Users, permission: 'users.view' },
            { label: 'Roles & permissions', to: '/admin/roles', icon: ShieldCheck, permission: 'roles.view' },
        ],
    },
];
