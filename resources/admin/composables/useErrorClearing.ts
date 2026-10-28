import { watch, type Ref } from 'vue';
import type { ValidationErrors } from '@admin/lib/http';

/**
 * Drops each validation message as soon as its field is edited, so a fixed
 * field no longer shows the old error. Keys follow Laravel's dot notation
 * ("name.bn", "types.0.prices.1.price", "sections.2.content.title") and are
 * read from the data that was sent.
 */
export function useErrorClearing(payload: () => unknown, errors: Ref<ValidationErrors>): void {
    let sent: unknown = null;

    const pick = (source: unknown, key: string): unknown =>
        key.split('.').reduce<unknown>((value, part) => (value !== null && typeof value === 'object' ? (value as Record<string, unknown>)[part] : undefined), source);
    const same = (a: unknown, b: unknown): boolean => JSON.stringify(a ?? null) === JSON.stringify(b ?? null);

    // A fresh set of errors: remember the values they were about.
    watch(errors, (next) => {
        sent = Object.keys(next).length ? JSON.parse(JSON.stringify(payload() ?? null)) : null;
    });

    watch(
        payload,
        (current) => {
            if (sent === null) return;
            const edited = Object.keys(errors.value).filter((key) => !same(pick(current, key), pick(sent, key)));
            if (!edited.length) return;
            const next = { ...errors.value };
            edited.forEach((key) => delete next[key]);
            errors.value = next;
        },
        { deep: true },
    );
}
