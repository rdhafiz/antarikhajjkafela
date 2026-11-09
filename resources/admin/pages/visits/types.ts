/** Filters shared by the three tabs; every one lives in the address bar. */
export const DRILL_KEYS = ['channel', 'source', 'in_app', 'subject_type', 'subject_id', 'device_type', 'browser', 'locale', 'visitor_id', 'session_id', 'ip'] as const;
export type DrillKey = (typeof DRILL_KEYS)[number];

export type VisitFilters = { view: 'sources' | 'pages' | 'log'; date_from: string; date_to: string; include_bots: string } & Record<DrillKey, string>;

export interface VisitTotals {
    views: number;
    visitors: number;
    sessions: number;
    bot_views: number;
    bookings: number;
    enquiries: number;
    storage: { database: string; separate: boolean };
}

export interface ChannelRow { channel: string; label: string; views: number; visitors: number; sessions: number; bookings: number; enquiries: number }
export interface SourceRow { channel: string; label: string; source: string; views: number; visitors: number; bookings: number; enquiries: number }
export interface AppRow { app: string; views: number; visitors: number }
export interface PageRow { subject_type: string; subject_id: number | null; title: string | null; path: string; url: string; deleted: boolean; views: number; visitors: number; last_seen: string }

export interface VisitLocation { label: string; local?: boolean; country?: string; country_code?: string; city?: string; isp?: string }

export interface VisitRow {
    id: number;
    visited_at: string;
    subject_type: string;
    subject_id: number | null;
    title: string | null;
    deleted: boolean;
    path: string;
    url: string;
    referrer: string | null;
    visitor_id: string;
    session_id: string | null;
    ip: string | null;
    location: VisitLocation | null;
    device_type: string | null;
    platform: string | null;
    browser: string | null;
    is_bot: boolean;
    locale: string | null;
    channel: string;
    channel_label: string | null;
    source: string | null;
    in_app: string | null;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    converted: { bookings: number; enquiries: number };
}

/** The "Came from" line on a booking or enquiry (null when the visit was not recorded). */
export interface VisitLanding {
    channel: string;
    channel_label: string | null;
    source: string | null;
    landing: { title: string | null; url: string };
    visited_at: string | null;
}

export interface VisitFilterOptions {
    devices: string[];
    platforms: string[];
    browsers: string[];
    channels: { value: string; label: string }[];
    apps: string[];
    subjects: { value: string; label: string }[];
}

export const subjectLabel = (type: string): string => ({ packages: 'Packages list', campaigns: 'Campaigns list', booking: 'Booking form' })[type] ?? type.charAt(0).toUpperCase() + type.slice(1);
