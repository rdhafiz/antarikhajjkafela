import type { StaffNote } from '@admin/components/ui/NotesPanel.vue';
export type InquiryStatus = 'new' | 'in_progress' | 'closed';

export interface InquiryRow {
    id: number;
    source: 'contact_form' | 'lead_form' | string;
    /** Staff notes (in the detail response; null in the list). */
    notes?: StaffNote[] | null;
    name: string | null;
    phone: string | null;
    email: string | null;
    data: Record<string, string>;
    page: { id: number; title: string; type: string } | null;
    status: InquiryStatus;
    /** The booking made from this inquiry. */
    booking?: { id: number; reference: string | null } | null;
    locale: string | null;
    read: boolean;
    created_at: string;
}

export const inquiryStatus: Record<InquiryStatus, { label: string; tone: string }> = {
    new: { label: 'New', tone: '#fbbf24' },
    in_progress: { label: 'In progress', tone: '#60a5fa' },
    closed: { label: 'Closed', tone: '#94a3b8' },
};

const sources: Record<string, { label: string; tone: string }> = {
    contact_form: { label: 'Contact form', tone: '#38bdf8' },
    fare_quote: { label: 'Air ticket quote', tone: '#34d399' },
    visa_enquiry: { label: 'Visa enquiry', tone: '#fbbf24' },
    campaign_form: { label: 'Campaign form', tone: '#a78bfa' },
    lead_form: { label: 'Enquiry form', tone: '#fb923c' },
};

export function inquirySource(source: string): { label: string; tone: string } {
    return sources[source] ?? { label: source.replace(/[_-]/g, ' ').replace(/^./, (c) => c.toUpperCase()), tone: '#fb923c' };
}

/** wa.me link for a Bangladeshi or international number. */
export function whatsappLink(phone: string | null): string | null {
    const digits = (phone ?? '').replace(/\D/g, '');
    if (!digits) return null;
    return `https://wa.me/${digits.startsWith('880') ? digits : digits.startsWith('0') ? `88${digits}` : digits}`;
}
