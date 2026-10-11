import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@admin/stores/auth';
import { useAppStore } from '@admin/stores/app';

declare module 'vue-router' {
    interface RouteMeta {
        /** Pages for signed-out visitors (login, password reset). */
        guest?: boolean;
        /** Permission required to open the page. */
        permission?: string;
        title?: string;
    }
}

const routes: RouteRecordRaw[] = [
    {
        path: '/admin',
        component: () => import('@admin/layouts/AuthLayout.vue'),
        meta: { guest: true },
        children: [
            { path: 'login', name: 'login', component: () => import('@admin/pages/auth/LoginPage.vue'), meta: { guest: true, title: 'Sign in' } },
            { path: 'forgot-password', name: 'forgot-password', component: () => import('@admin/pages/auth/ForgotPasswordPage.vue'), meta: { guest: true, title: 'Forgot password' } },
            { path: 'reset-password', name: 'reset-password', component: () => import('@admin/pages/auth/ResetPasswordPage.vue'), meta: { guest: true, title: 'Reset password' } },
        ],
    },
    {
        path: '/admin',
        component: () => import('@admin/layouts/AdminLayout.vue'),
        children: [
            { path: '', name: 'dashboard', component: () => import('@admin/pages/DashboardPage.vue'), meta: { title: 'Dashboard' } },
            { path: 'profile', name: 'profile', component: () => import('@admin/pages/ProfilePage.vue'), meta: { title: 'My profile' } },
            { path: 'media', name: 'media', component: () => import('@admin/pages/media/MediaPage.vue'), meta: { title: 'Media library', permission: 'media.view' } },
            { path: 'pages', name: 'pages', component: () => import('@admin/pages/content/PageListPage.vue'), meta: { title: 'Pages', permission: 'pages.view' } },
            { path: 'campaigns', name: 'campaigns', component: () => import('@admin/pages/content/PageListPage.vue'), meta: { title: 'Campaign pages', permission: 'campaigns.view' } },
            { path: 'pages/:id(\d+)', name: 'page-editor', component: () => import('@admin/pages/content/PageEditorPage.vue'), meta: { title: 'Edit page' } },
            { path: 'services', name: 'services', component: () => import('@admin/pages/travel/ServicesPage.vue'), meta: { title: 'Services', permission: 'services.view' } },
            { path: 'packages', name: 'packages', component: () => import('@admin/pages/travel/PackagesPage.vue'), meta: { title: 'Packages', permission: 'packages.view' } },
            { path: 'packages/:id(\d+)', name: 'package-editor', component: () => import('@admin/pages/travel/PackageEditorPage.vue'), meta: { title: 'Edit package', permission: 'packages.view' } },
            { path: 'inquiries', name: 'inquiries', component: () => import('@admin/pages/inquiries/InquiriesPage.vue'), meta: { title: 'Inquiries', permission: 'inquiries.view' } },
            { path: 'menus/:location?', name: 'menus', component: () => import('@admin/pages/menus/MenusPage.vue'), meta: { title: 'Menus', permission: 'menus.view' } },
            { path: 'settings/:group?', name: 'settings', component: () => import('@admin/pages/settings/SettingsPage.vue'), meta: { title: 'Settings', permission: 'settings.view' } },
            { path: 'users', name: 'users', component: () => import('@admin/pages/users/UsersPage.vue'), meta: { title: 'Users', permission: 'users.view' } },
            { path: 'roles', name: 'roles', component: () => import('@admin/pages/roles/RolesPage.vue'), meta: { title: 'Roles & permissions', permission: 'roles.view' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@admin/pages/NotFoundPage.vue'), meta: { title: 'Not found' } },
        ],
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    const signedIn = await auth.check();
    const isGuestPage = to.matched.some((record) => record.meta.guest);

    if (!signedIn && !isGuestPage) {
        return { name: 'login', query: to.fullPath !== '/admin' ? { redirect: to.fullPath } : {} };
    }

    if (signedIn && isGuestPage) {
        return { name: 'dashboard' };
    }

    if (signedIn) {
        await useAppStore().load();
    }

    if (to.meta.permission && !auth.can(to.meta.permission)) {
        return { name: 'not-found', params: { pathMatch: to.path.replace(/^\/admin\/?/, '').split('/') } };
    }

    return true;
});

router.afterEach((to) => {
    const app = useAppStore();
    document.title = [to.meta.title, 'Admin', app.loaded ? app.name : null].filter(Boolean).join(' · ');
});
