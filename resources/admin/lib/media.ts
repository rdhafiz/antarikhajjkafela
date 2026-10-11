import { http } from '@admin/lib/http';
import type { Media, MediaUsage, Translations } from '@admin/types';

export interface VariantOverride {
    width: number | null;
    height: number | null;
    fit: 'scale' | 'cover';
    quality: number;
    crop?: { x: number; y: number; width: number; height: number } | null;
}

export interface UsagesResponse {
    data: MediaUsage[];
    mode: 'automatic' | 'manual';
    max_upload_kb: number;
    mimes: string[];
}

let usagesCache: Promise<UsagesResponse> | null = null;

export function fetchUsages(force = false): Promise<UsagesResponse> {
    if (!usagesCache || force) {
        usagesCache = http.get<UsagesResponse>('/media/usages').then((r) => r.data);
        usagesCache.catch(() => (usagesCache = null));
    }

    return usagesCache;
}

export async function uploadMedia(
    file: File,
    usage: string,
    options: { variants?: Record<string, VariantOverride>; alt?: Translations; onProgress?: (percent: number) => void } = {},
): Promise<Media> {
    const body = new FormData();
    body.append('file', file);
    body.append('usage', usage);

    if (options.variants) {
        body.append('variants', JSON.stringify(options.variants));
    }

    for (const [locale, text] of Object.entries(options.alt ?? {})) {
        if (text) body.append(`alt[${locale}]`, text);
    }

    const { data } = await http.post<{ data: Media }>('/media', body, {
        onUploadProgress: (event) => {
            if (event.total) options.onProgress?.(Math.round((event.loaded / event.total) * 100));
        },
    });

    return data.data;
}

/** Client-side checks so obviously invalid files fail fast. */
export function checkFile(file: File, limits: Pick<UsagesResponse, 'max_upload_kb' | 'mimes'>): string | null {
    if (!limits.mimes.includes(file.type)) {
        return `${file.name}: unsupported file type. Use JPG, PNG, WebP, GIF or AVIF.`;
    }

    if (file.size > limits.max_upload_kb * 1024) {
        return `${file.name}: larger than ${Math.round(limits.max_upload_kb / 1024)} MB.`;
    }

    return null;
}
