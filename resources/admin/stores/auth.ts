import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { http } from '@admin/lib/http';
import type { CurrentUser } from '@admin/types';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<CurrentUser | null>(null);
    const checked = ref(false);

    const isAuthenticated = computed(() => user.value !== null);

    /**
     * Take the user the bootstrap request carried, or null when it found
     * nobody signed in. The router does the asking so that booting the
     * admin costs one round trip rather than two.
     */
    function adopt(current: CurrentUser | null): void {
        user.value = current;
        checked.value = true;
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

    return { user, checked, isAuthenticated, adopt, login, logout, clear, setUser, can };
});
