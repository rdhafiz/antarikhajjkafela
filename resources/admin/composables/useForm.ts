import { reactive, ref, toRaw } from 'vue';
import { errorMessage, validationErrors, type ValidationErrors } from '@admin/lib/http';
import { useToastStore } from '@admin/stores/toast';
import { useErrorClearing } from '@admin/composables/useErrorClearing';

/**
 * Form state with Laravel validation error handling.
 *
 *   const form = useForm({ name: '' });
 *   await form.submit(() => http.post('/users', form.data));
 */
export function useForm<T extends object>(initial: T) {
    const data = reactive(structuredClone(toRaw(initial))) as T;
    const errors = ref<ValidationErrors>({});
    const processing = ref(false);
    useErrorClearing(() => data, errors);

    function error(field: string): string | undefined {
        return errors.value[field]?.[0];
    }

    function reset(values: T = initial): void {
        Object.assign(data, structuredClone(toRaw(values)));
        errors.value = {};
    }

    /**
     * Runs the request. Validation errors are stored on the form; any other
     * failure shows an error toast. Resolves to the result, or null on failure.
     */
    async function submit<R>(request: () => Promise<R>): Promise<R | null> {
        processing.value = true;
        errors.value = {};

        try {
            return await request();
        } catch (e) {
            const validation = validationErrors(e);

            if (validation) {
                errors.value = validation;
            } else {
                useToastStore().error(errorMessage(e));
            }

            return null;
        } finally {
            processing.value = false;
        }
    }

    // reactive() unwraps the refs so templates can read form.processing directly.
    return reactive({ data, errors, processing, error, reset, submit });
}
