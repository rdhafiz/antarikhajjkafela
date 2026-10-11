import { defineStore } from 'pinia';
import { ref } from 'vue';

export type ToastTone = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    tone: ToastTone;
    title: string;
    description?: string;
}

let nextId = 1;

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([]);

    function push(tone: ToastTone, title: string, description?: string, timeout = 4500): void {
        const id = nextId++;
        toasts.value.push({ id, tone, title, description });

        if (timeout > 0) {
            window.setTimeout(() => dismiss(id), timeout);
        }
    }

    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    return {
        toasts,
        dismiss,
        success: (title: string, description?: string) => push('success', title, description),
        error: (title: string, description?: string) => push('error', title, description, 7000),
        info: (title: string, description?: string) => push('info', title, description),
    };
});
