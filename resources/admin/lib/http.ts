import axios, { AxiosError } from 'axios';

/**
 * API client for the admin portal. Authentication uses Laravel's session
 * cookie (Sanctum SPA mode), so no tokens are stored in the browser.
 */
export const http = axios.create({
    baseURL: '/api/admin',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let csrfReady: Promise<unknown> | null = null;

/** Fetch the XSRF-TOKEN cookie once before the first state-changing request. */
export function ensureCsrfCookie(force = false): Promise<unknown> {
    if (!csrfReady || force) {
        csrfReady = axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    }

    return csrfReady;
}

http.interceptors.request.use(async (config) => {
    if (config.method && config.method.toLowerCase() !== 'get') {
        await ensureCsrfCookie();
    }

    return config;
});

let onUnauthenticated: () => void = () => {};

export function setUnauthenticatedHandler(handler: () => void): void {
    onUnauthenticated = handler;
}

http.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const status = error.response?.status;
        const config = error.config as (typeof error.config & { _retried?: boolean }) | undefined;

        // CSRF token expired: refresh it and retry once.
        if (status === 419 && config && !config._retried) {
            config._retried = true;
            await ensureCsrfCookie(true);

            return http(config);
        }

        if (status === 401) {
            onUnauthenticated();
        }

        return Promise.reject(error);
    },
);

export type ValidationErrors = Record<string, string[]>;

/** Laravel validation errors from a failed request, or null. */
export function validationErrors(error: unknown): ValidationErrors | null {
    if (error instanceof AxiosError && error.response?.status === 422) {
        return (error.response.data as { errors?: ValidationErrors }).errors ?? {};
    }

    return null;
}

/** A human readable message for any failed request. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
    if (error instanceof AxiosError) {
        if (!error.response) {
            return 'Network error. Check your connection and try again.';
        }

        const data = error.response.data as { message?: string } | undefined;

        if (error.response.status === 403) {
            return data?.message && data.message !== 'This action is unauthorized.'
                ? data.message
                : 'You do not have permission to do that.';
        }

        if (error.response.status === 413) {
            return 'The file is too large to upload.';
        }

        if (data?.message) {
            return data.message;
        }
    }

    return fallback;
}

export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}
