<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useRouter } from 'vue-router';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { CalendarDays, ChevronRight, EllipsisVertical, ExternalLink, Mail, MessageCircle, Package as PackageIcon, Phone, Users } from 'lucide-vue-next';
import { formatDate } from '@admin/lib/format';
import { money, type BookingRow } from './types';

/**
 * One booking: a wide row when the list has room (container queries), a card otherwise.
 */
const props = defineProps<{
    row: BookingRow;
    statusLabel: string;
    status: { icon: Component; tone: string };
    index: number;
}>();
const emit = defineEmits<{ open: [] }>();

const router = useRouter();

const paidPercent = computed(() => (props.row.total > 0 ? Math.min(100, Math.round((props.row.paid_amount / props.row.total) * 100)) : 0));
/** Colour and words for how much is paid. */
const payment = computed(() => {
    if (props.row.overpaid > 0) return { label: `${money(props.row.overpaid)} overpaid`, tone: '#fb923c' };
    if (props.row.total > 0 && props.row.balance <= 0) return { label: 'Paid in full', tone: '#34d399' };
    if (props.row.paid_amount > 0) return { label: `${paidPercent.value}% paid`, tone: '#fbbf24' };
    return { label: 'Nothing paid yet', tone: '#60a5fa' };
});

const whatsapp = computed(() => {
    const digits = (props.row.customer_phone ?? '').replace(/\D/g, '');
    return digits ? `https://wa.me/${digits.startsWith('0') ? `88${digits}` : digits}` : null;
});
const travellers = computed(() => `${props.row.travelers} ${props.row.travelers === 1 ? 'traveller' : 'travellers'}`);
</script>

<template>
    <article class="bk-item group" :class="{ 'is-unread': row.is_unread }" :style="{ '--i': index, '--pay': payment.tone, '--st': status.tone }" @click="emit('open')">
        <div class="bk-media">
            <img v-if="row.package_photo" :src="row.package_photo" alt="" class="bk-photo" loading="lazy" />
            <div v-else class="bk-photo bk-photo-empty"><PackageIcon class="size-6" aria-hidden="true" /></div>
        </div>

        <div class="bk-main">
            <h2 class="bk-name">
                <span v-if="row.is_unread" class="bk-dot" title="New booking"><span class="sr-only">New booking: </span></span>
                <span class="truncate">{{ row.customer_name }}</span>
            </h2>
            <p class="bk-meta">
                <span class="font-semibold text-ink tabular-nums">{{ row.reference }}</span>
                <span class="bk-booked-inline"> · {{ formatDate(row.created_at, true) }}</span>
            </p>
            <p class="bk-tags">
                <span class="bk-tag" style="--chip: #a78bfa" :title="[row.package, row.option].filter(Boolean).join(' · ')"><PackageIcon class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ row.package ?? 'Package' }}<template v-if="row.option"> · {{ row.option }}</template></span></span>
                <span class="bk-tag" style="--chip: #38bdf8"><Users class="size-3.5 shrink-0" aria-hidden="true" />{{ travellers }}</span>
            </p>
        </div>

        <div class="bk-cell bk-amount-cell">
            <div class="bk-amount">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="bk-label">Total</span>
                    <span class="text-[12px] font-semibold">{{ payment.label }}</span>
                </div>
                <p class="mt-0.5 text-[18px] leading-tight font-bold whitespace-nowrap text-ink tabular-nums">{{ money(row.total) }}</p>
                <div class="bk-bar" role="progressbar" :aria-valuenow="paidPercent" aria-valuemin="0" aria-valuemax="100" :aria-label="`Paid ${paidPercent}%`">
                    <span :style="{ width: `${paidPercent}%` }" />
                </div>
                <p class="mt-1.5 text-[12px] whitespace-nowrap text-ink-soft tabular-nums">
                    Paid {{ money(row.paid_amount) }}<template v-if="row.balance > 0"> · Due <strong class="font-semibold text-ink">{{ money(row.balance) }}</strong></template>
                </p>
            </div>
        </div>

        <div class="bk-cell bk-status">
            <span class="bk-label">Status</span>
            <span class="bk-pill"><component :is="status.icon" class="size-4" aria-hidden="true" />{{ statusLabel }}</span>
        </div>

        <div class="bk-cell bk-booked">
            <CalendarDays class="size-5 shrink-0 text-ink-soft" aria-hidden="true" />
            <span>
                <span class="bk-label">Booked</span>
                <span class="block text-[14px] font-medium whitespace-nowrap text-ink">{{ formatDate(row.created_at) }}</span>
            </span>
        </div>

        <div class="bk-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="bk-icon-btn" :aria-label="`More actions for booking ${row.reference}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="router.push({ name: 'booking', params: { id: row.id } })">
                            <span class="lm-icon" style="--tone: #818cf8"><ExternalLink class="size-4" /></span>Open booking
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="row.customer_phone" as="a" :href="`tel:${row.customer_phone}`" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><Phone class="size-4" /></span>Call {{ row.customer_phone }}
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="whatsapp" as="a" :href="whatsapp" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #34d399"><MessageCircle class="size-4" /></span>WhatsApp the customer
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="row.customer_email" as="a" :href="`mailto:${row.customer_email}`" class="lm-item">
                            <span class="lm-icon" style="--tone: #f472b6"><Mail class="size-4" /></span>Email the customer
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="bk-icon-btn bk-open" :aria-label="`Open booking ${row.reference}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Narrow (default): a card. */
.bk-item {
    position: relative;
    display: grid;
    grid-template-columns: 4.5rem minmax(0, 1fr) auto;
    grid-template-areas:
        'media main main'
        'amount amount amount'
        'status status actions';
    align-items: center;
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: bk-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-unread {
        border-color: color-mix(in oklab, var(--admin-primary) 30%, white);
    }
}

.bk-media {
    grid-area: media;
    align-self: start;
    position: relative;
    margin: 1rem 0 0 1rem;
    width: 3.5rem;
    height: 3.5rem;
    overflow: hidden;
    border-radius: 0.9rem;
    background: var(--color-canvas);
}

.bk-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1);
}

.bk-item:hover .bk-photo {
    transform: scale(1.06);
}

.bk-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6d28d9;
    background: linear-gradient(135deg, #ede9fe, #f5f3ff);
}

.bk-main {
    grid-area: main;
    min-width: 0;
    padding: 1rem 1.1rem 0 0.85rem;
}

.bk-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--color-ink);
}

.bk-dot {
    width: 0.55rem;
    height: 0.55rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: var(--admin-primary);
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--admin-primary) 18%, transparent);
}

.bk-meta {
    margin-top: 0.15rem;
    font-size: 13px;
    color: var(--color-ink-soft);
}

.bk-tags {
    margin-top: 0.6rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.bk-tag {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.25rem 0.65rem;
    font-size: 12.5px;
    font-weight: 600;
    background: color-mix(in oklab, var(--chip) 13%, white);
    color: color-mix(in oklab, var(--chip) 45%, black);
    white-space: nowrap;
}

.bk-cell {
    min-width: 0;
    padding: 0.5rem 1.1rem;
}

.bk-amount-cell {
    grid-area: amount;
    padding-top: 0.85rem;
}

.bk-amount {
    border-radius: 0.9rem;
    padding: 0.7rem 0.95rem 0.75rem;
    background: color-mix(in oklab, var(--pay) 12%, white);
    color: color-mix(in oklab, var(--pay) 45%, black);
}

.bk-bar {
    margin-top: 0.55rem;
    height: 0.4rem;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in oklab, var(--pay) 22%, white);

    > span {
        display: block;
        height: 100%;
        min-width: 0.4rem;
        border-radius: 999px;
        background: color-mix(in oklab, var(--pay) 75%, black 10%);
        transition: width 600ms cubic-bezier(0.22, 1, 0.36, 1);
    }
}

.bk-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.bk-amount .bk-label {
    color: inherit;
}

.bk-status {
    grid-area: status;
    padding-bottom: 1rem;
}

.bk-status .bk-label,
.bk-booked {
    display: none;
}

.bk-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    background: color-mix(in oklab, var(--st) 15%, white);
    color: color-mix(in oklab, var(--st) 42%, black);
}

.bk-booked {
    grid-area: booked;
    align-items: center;
    gap: 0.65rem;
}

.bk-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0 0.9rem 1rem 0;
    cursor: default;
}

.bk-icon-btn {
    display: flex;
    width: 2.6rem;
    height: 2.6rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: var(--color-ink-soft);
    transition: background-color 160ms ease, color 160ms ease, transform 200ms ease;

    &:hover,
    &[data-state='open'] {
        background: var(--color-canvas);
        color: var(--color-ink);
    }
}

.bk-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .bk-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* Medium: one row; the booked date stays in the details line. */
@container bk-list (min-width: 820px) {
    .bk-item {
        grid-template-columns: 7.5rem minmax(0, 1fr) minmax(220px, 260px) auto auto;
        grid-template-areas: 'media main amount status actions';
        min-height: 124px;
    }

    .bk-media {
        align-self: stretch;
        margin: 0;
        width: auto;
        height: auto;
        border-radius: 0;
    }

    .bk-main {
        padding: 1rem 1.25rem;
    }

    .bk-amount-cell {
        padding: 0.75rem 0.5rem 0.75rem 0;
    }

    .bk-status {
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.1rem;
        margin-left: 0.6rem;
    }

    .bk-status .bk-label {
        display: block;
        margin-bottom: 0.4rem;
    }

    .bk-actions {
        padding: 0 1rem 0 0.25rem;
    }
}

/* Wide: the booked date gets its own column. */
@container bk-list (min-width: 1180px) {
    .bk-item {
        grid-template-columns: 8.5rem minmax(0, 1fr) minmax(240px, 280px) auto auto auto;
        grid-template-areas: 'media main amount status booked actions';
    }

    .bk-booked {
        display: flex;
        align-self: stretch;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .bk-booked-inline {
        display: none;
    }
}

@keyframes bk-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .bk-item {
        animation: none;
    }

    .bk-item,
    .bk-photo,
    .bk-open,
    .bk-bar > span {
        transition: none;
    }

    .bk-item:hover,
    .bk-item:hover .bk-photo {
        transform: none;
    }
}
</style>
