export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}

const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const dateTimeFormat = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) return '—';

    return (withTime ? dateTimeFormat : dateFormat).format(new Date(value));
}

const timeFormat = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' });

export function formatTime(value: string | null | undefined): string {
    return value ? timeFormat.format(new Date(value)) : '';
}

/**
 * Dates written as 2026-12-20 inside a value (form answers such as a travel
 * date or "Flight 2: Dubai → Istanbul · 2026-12-20") in the admin's
 * "20 Dec 2026" style; everything else is left as it is.
 */
export function formatDatesIn(value: unknown): string {
    return String(value ?? '').replace(/\b(\d{4})-(\d{2})-(\d{2})\b/g, (match, y: string, m: string, d: string) => {
        const date = new Date(Number(y), Number(m) - 1, Number(d));
        return Number.isNaN(date.getTime()) || date.getDate() !== Number(d) ? match : dateFormat.format(date);
    });
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

export function titleCase(value: string): string {
    return value.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
