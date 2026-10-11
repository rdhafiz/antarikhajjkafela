import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { http } from '@admin/lib/http';
import type { CurrentUser } from '@admin/types';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<CurrentUser | null>(null);
    const checked = ref(false);

    const isAuthenticated = computed(() => user.value !== null);

    /** Load the signed-in user once; resolves to false when not signed in. */
    async function check(): Promise<boolean> {
        if (checked.value) return isAuthenticated.value;

        try {
            const { data } = await http.get<{ data: CurrentUser }>('/auth/me');
            user.value = data.data;
        } catch {
            user.value = null;
        } finally {
            checked.value = true;
        }

        return isAuthenticated.value;
    }

    async function login(credentials: { email: string; password: string; remember: boolean }): Promise<void> {
        const { data } = await http.post<{ data: CurrentUser }>('/auth/login', credentials);
        user.value = data.data;
        checked.value = true;
    }

    async function logout(): Promise<void> {
        try {
            await http.post('/auth/logout');
        } finally {
            clear();
        }
    }

    function clear(): void {
        user.value = null;
        checked.value = true;
    }

    function setUser(value: CurrentUser): void {
        user.value = value;
    }

    /** True when the user holds the permission (super admins hold all). */
    function can(permission: string | undefined | null): boolean {
        if (!permission) return true;
        if (!user.value) return false;

        return user.value.permissions.includes('*') || user.value.permissions.includes(permission);
    }

    return { user, checked, isAuthenticated, check, login, logout, clear, setUser, can };
});
