import { ref } from 'vue';

export interface ConfirmOptions {
    title: string;
    message?: string;
    confirmLabel?: string;
    tone?: 'danger' | 'primary';
    /** Information only: one button that closes the dialog (resolves false). */
    notice?: boolean;
    /** A short list under the message, e.g. the places an image is used. */
    items?: string[];
}

interface PendingConfirm extends ConfirmOptions {
    resolve: (value: boolean) => void;
}

const pending = ref<PendingConfirm | null>(null);

/**
 * Promise-based confirmation dialog:
 *   if (await confirm({ title: 'Delete user?' })) { ... }
 */
export function useConfirm() {
    function confirm(options: ConfirmOptions): Promise<boolean> {
        pending.value?.resolve(false);

        return new Promise((resolve) => {
            pending.value = { tone: 'danger', confirmLabel: 'Confirm', ...options, resolve };
        });
    }

    function settle(value: boolean): void {
        pending.value?.resolve(value);
        pending.value = null;
    }

    return { pending, confirm, settle };
}
