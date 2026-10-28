import type { StaffNote } from '@admin/components/ui/NotesPanel.vue';
export type BookingStatus = 'pending' | 'confirmed' | 'partially_paid' | 'advance_paid' | 'fully_paid' | 'completed' | 'cancelled';

export const statusLabels: Record<BookingStatus, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    partially_paid: 'Partially paid',
    advance_paid: 'Advance paid',
    fully_paid: 'Fully paid',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const statusTone: Record<BookingStatus, 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'gold'> = {
    pending: 'warning',
    confirmed: 'brand',
    partially_paid: 'neutral',
    advance_paid: 'gold',
    fully_paid: 'success',
    completed: 'success',
    cancelled: 'danger',
};

const taka = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

export function money(value: number): string {
    return `৳${taka.format(value)}`;
}

export interface BookingRow {
    id: number;
    reference: string;
    status: BookingStatus;
    package: string | null;
    option: string;
    travelers: number;
    /** "November 2026" or "Hajj 2028". */
    travel_date: string | null;
    total: number;
    paid_amount: number;
    balance: number;
    /** Paid above the total. Zero unless a payment predates the cap. */
    overpaid: number;
    customer_name: string;
    customer_phone: string;
    customer_email?: string | null;
    /** The package's banner (small), when it has one. */
    package_photo?: string | null;
    is_unread: boolean;
    created_at: string;
}

export interface BookingPayment {
    id: number;
    method: string;
    method_label: string;
    is_online: boolean;
    status: 'initiated' | 'paid' | 'failed' | 'cancelled';
    amount: number;
    transaction_id: string | null;
    gateway_reference: string | null;
    reference: string | null;
    note: string | null;
    recorded_by: string | null;
    paid_at: string | null;
    created_at: string;
    log: { at: string; event: string; data: unknown }[];
}

/** One entry of the customer-facing history (also shown on the customer's booking page). */
export interface BookingUpdate {
    id: number;
    kind: 'created' | 'status' | 'payment' | 'message';
    status: BookingStatus | null;
    message: string | null;
    amount: number | null;
    /** An email was asked for; it goes out just after the save, so emailed turns true a moment later. */
    notify: boolean;
    emailed: boolean;
    by: string | null;
    created_at: string;
}

export interface BookingDetail extends BookingRow {
    package_id: number | null;
    package_url: string | null;
    public_url: string;
    unit: 'person' | 'room' | 'group';
    unit_price: number;
    quantity: number;
    advance_type: 'none' | 'fixed' | 'percentage';
    advance_value: number | null;
    advance_amount: number;
    currency: string;
    customer_email: string | null;
    customer_address: string | null;
    customer_note: string | null;
    /** Staff notes, newest first. */
    notes: StaffNote[];
    locale: string;
    documents: { id: number; kind: string; kind_label: string; name: string; size: number; is_image: boolean; url: string }[];
    payments: BookingPayment[];
    offline_methods: Record<string, string>;
    updates: BookingUpdate[];
    activity: BookingActivity[];
}

/** One entry of the booking's own activity log (staff, payments, emails, customer). */
export interface BookingActivity {
    id: number;
    event: string;
    category: 'staff' | 'payment' | 'email' | 'customer';
    label: string;
    by: string | null;
    details: Record<string, string | number | boolean | null>;
    at: string;
}
