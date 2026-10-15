<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import {
    BedDouble,
    Building2,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarX,
    ChevronRight,
    CircleCheck,
    EyeOff,
    Copy,
    ExternalLink,
    FileText,
    EllipsisVertical,
    Package as PackageIcon,
    PencilLine,
    Pencil,
    Plane,
    Star,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { formatDate } from '@admin/lib/format';

export interface PackageRow {
    id: number;
    name: string;
    service: string | null;
    url: string;
    status: 'draft' | 'published';
    is_featured: boolean;
    show_price: boolean;
    booking_state: 'open' | 'upcoming' | 'closed';
    duration_days: number | null;
    min_price: number | null;
    types_count: number;
    banner_url: string | null;
    prices_count: number;
    hotels_count: number;
    flights_count: number;
    sections_count: number;
    updated_at: string;
}

/**
 * One package: a wide row on large screens (list view), a card otherwise
 * (grid view, and every view on phones and tablets).
 */
const props = defineProps<{
    row: PackageRow;
    layout: 'list' | 'grid';
    /** Colour of the price box (one per service). */
    tone: string;
    index: number;
    canCreate: boolean;
    canDelete: boolean;
}>();
const emit = defineEmits<{ open: []; duplicate: []; remove: [] }>();

const router = useRouter();
const money = new Intl.NumberFormat('en-IN');

const booking = computed(
    () =>
        ({
            open: { label: 'Booking open', icon: CalendarCheck, cls: 'is-green' },
            upcoming: { label: 'Opens soon', icon: CalendarClock, cls: 'is-amber' },
            closed: { label: 'Booking closed', icon: CalendarX, cls: 'is-rose' },
        })[props.row.booking_state],
);
const status = computed(() =>
    props.row.status === 'published' ? { label: 'Published', icon: CircleCheck, cls: 'is-green' } : { label: 'Draft', icon: PencilLine, cls: 'is-slate' },
);

const plural = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;
/** What the package includes, as colourful chips (grey when missing). */
const includes = computed(() => [
    { key: 'rooms', icon: BedDouble, tone: '#a78bfa', label: plural(props.row.prices_count, 'room option', 'room options'), count: props.row.prices_count },
    { key: 'flights', icon: Plane, tone: '#38bdf8', label: plural(props.row.flights_count, 'flight', 'flights'), count: props.row.flights_count },
    { key: 'hotels', icon: Building2, tone: '#fb923c', label: plural(props.row.hotels_count, 'hotel', 'hotels'), count: props.row.hotels_count },
    { key: 'sections', icon: FileText, tone: '#34d399', label: plural(props.row.sections_count, 'page section', 'page sections'), count: props.row.sections_count },
]);

const meta = computed(() =>
    [props.row.service, plural(props.row.types_count, 'type', 'types'), props.row.duration_days ? `${props.row.duration_days} days` : null].filter(Boolean).join(' · '),
);
</script>

<template>
    <article
        class="pk-item group"
        :class="layout === 'list' ? 'is-list' : 'is-grid'"
        :style="{ '--tone': tone, '--i': index }"
        @click="emit('open')"
    >
        <!-- Faint banner echo on wide rows -->
        <img v-if="row.banner_url && layout === 'list'" :src="row.banner_url" alt="" class="pk-echo" aria-hidden="true" loading="lazy" />

        <div class="pk-media">
            <img v-if="row.banner_url" :src="row.banner_url" :alt="''" class="pk-photo" loading="lazy" />
            <div v-else class="pk-photo pk-photo-empty"><PackageIcon class="size-8" aria-hidden="true" /></div>
            <span v-if="row.is_featured" class="pk-star" title="Featured"><Star class="size-4 fill-current" aria-hidden="true" /><span class="sr-only">Featured</span></span>
        </div>

        <div class="pk-main">
            <h2 class="pk-name">{{ row.name }}</h2>
            <p class="pk-meta">{{ meta }}<span class="pk-updated-inline"> · Updated {{ formatDate(row.updated_at) }}</span></p>
            <ul class="pk-chips" aria-label="Included">
                <li v-for="chip in includes" :key="chip.key" class="pk-chip" :class="{ 'is-off': !chip.count }" :style="{ '--chip': chip.tone }" :title="chip.count ? chip.label : `No ${chip.label.replace(/^0 /, '')}`">
                    <component :is="chip.icon" class="size-4" aria-hidden="true" />
                    <span class="sr-only">{{ chip.count ? chip.label : `No ${chip.label.replace(/^0 /, '')}` }}</span>
                </li>
            </ul>
        </div>

        <div class="pk-cell pk-price-cell">
            <div class="pk-price">
                <Wallet class="size-6 shrink-0" aria-hidden="true" />
                <span class="min-w-0">
                    <span class="pk-label">From</span>
                    <span class="block text-[19px] leading-tight font-bold whitespace-nowrap text-ink tabular-nums">{{ row.min_price !== null ? `৳${money.format(row.min_price)}` : '—' }}</span>
                    <span v-if="!row.show_price" class="mt-0.5 flex items-center gap-1 text-[12px] font-semibold whitespace-nowrap text-amber-700"><EyeOff class="size-3.5" aria-hidden="true" />Hidden on website</span>
                </span>
            </div>
        </div>

        <div class="pk-cell pk-booking">
            <span class="pk-label">Booking</span>
            <span class="pk-pill" :class="booking.cls"><component :is="booking.icon" class="size-4" aria-hidden="true" />{{ booking.label }}</span>
        </div>

        <div class="pk-cell pk-status">
            <span class="pk-label">Status</span>
            <span class="pk-pill" :class="status.cls"><component :is="status.icon" class="size-4" aria-hidden="true" />{{ status.label }}</span>
        </div>

        <div class="pk-cell pk-updated">
            <CalendarDays class="size-5 shrink-0 text-ink-soft" aria-hidden="true" />
            <span>
                <span class="pk-label">Updated</span>
                <span class="block text-[14px] font-medium whitespace-nowrap text-ink">{{ formatDate(row.updated_at) }}</span>
            </span>
        </div>

        <div class="pk-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="pk-icon-btn" :aria-label="`More actions for ${row.name}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-52 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="router.push({ name: 'package-editor', params: { id: row.id } })">
                            <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit package
                        </DropdownMenuItem>
                        <DropdownMenuItem as="a" :href="row.url" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="canCreate" class="lm-item" @select="emit('duplicate')">
                            <span class="lm-icon" style="--tone: #a78bfa"><Copy class="size-4" /></span>Duplicate as a draft
                        </DropdownMenuItem>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete package
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="pk-icon-btn pk-open" :aria-label="`Open ${row.name}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
.pk-item {
    position: relative;
    isolation: isolate;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas:
        'media media'
        'main main'
        'price price'
        'booking status'
        'updated actions';
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: pk-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 45ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }
}

.pk-media {
    grid-area: media;
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: var(--color-canvas);
}

.pk-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1);
}

.pk-item:hover .pk-photo {
    transform: scale(1.05);
}

.pk-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: color-mix(in oklab, var(--tone) 55%, black);
    background: linear-gradient(135deg, color-mix(in oklab, var(--tone) 22%, white), color-mix(in oklab, var(--tone) 8%, white));
}

.pk-star {
    position: absolute;
    top: 0.65rem;
    left: 0.65rem;
    display: flex;
    width: 2.1rem;
    height: 2.1rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    background: #fff;
    color: #eab308;
    box-shadow: 0 4px 12px rgb(15 23 42 / 0.18);
}

.pk-main {
    grid-area: main;
    min-width: 0;
    padding: 1rem 1.1rem 0.4rem;
}

.pk-name {
    font-size: 17px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--color-ink);
}

.pk-meta {
    margin-top: 0.2rem;
    font-size: 13.5px;
    color: var(--color-ink-soft);
}

.pk-chips {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
}

.pk-chip {
    display: flex;
    width: 2.1rem;
    height: 2.1rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: color-mix(in oklab, var(--chip) 15%, white);
    color: color-mix(in oklab, var(--chip) 55%, black);

    &.is-off {
        background: var(--color-canvas);
        color: #94a3b8;
    }
}

.pk-cell {
    min-width: 0;
    padding: 0.5rem 1.1rem;
}

.pk-price-cell {
    grid-area: price;
    padding-top: 0.65rem;
}

.pk-price {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    border-radius: 0.9rem;
    padding: 0.75rem 1rem;
    background: color-mix(in oklab, var(--tone) 13%, white);
    color: color-mix(in oklab, var(--tone) 55%, black);
}

.pk-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.pk-booking {
    grid-area: booking;
}

.pk-status {
    grid-area: status;
}

.pk-pill {
    margin-top: 0.35rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;

    &.is-green {
        background: #e8f8ef;
        color: #157347;
    }
    &.is-amber {
        background: #fef5e2;
        color: #8a5a00;
    }
    &.is-rose {
        background: #fdecef;
        color: #b4233f;
    }
    &.is-slate {
        background: #eef1f5;
        color: #475569;
    }
}

.pk-updated {
    grid-area: updated;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 1rem;
}

.pk-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.5rem 0.9rem 1rem;
    cursor: default;
}

.pk-icon-btn {
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

.pk-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .pk-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

.pk-echo {
    display: none;
}

/* Rows (list view), sized by the space the list has (container queries), not the window. */
.pk-updated-inline {
    display: none;
}

@container pk-list (min-width: 860px) {
    /* Medium width: booking and status share one column; the updated date joins the details line. */
    .pk-item.is-list {
        grid-template-columns: 184px minmax(0, 1fr) auto auto auto;
        grid-template-rows: 1fr 1fr;
        grid-template-areas:
            'media main price booking actions'
            'media main price status actions';
        align-items: center;
        min-height: 136px;

        .pk-media {
            align-self: stretch;
            aspect-ratio: auto;
        }

        .pk-main {
            padding: 1rem 1.25rem;
        }

        .pk-cell {
            padding: 0.2rem 1rem;
        }

        .pk-price-cell {
            padding: 0 1rem 0 0;
        }

        .pk-booking,
        .pk-status {
            border-left: 1px solid var(--color-line);
            padding-left: 1.1rem;
            align-self: stretch;
            display: flex;
            flex-direction: column;
        }

        .pk-booking {
            justify-content: flex-end;
            padding-bottom: 0.25rem;
        }

        .pk-status {
            justify-content: flex-start;
            padding-top: 0.25rem;
        }

        .pk-booking .pk-label,
        .pk-status .pk-label,
        .pk-updated {
            display: none;
        }

        .pk-updated-inline {
            display: inline;
        }

        .pk-pill {
            margin-top: 0;
        }

        .pk-actions {
            padding: 0 1rem 0 0.5rem;
        }

        .pk-echo {
            display: block;
            position: absolute;
            inset: 0 0 0 auto;
            z-index: -1;
            width: 34%;
            height: 100%;
            object-fit: cover;
            opacity: 0.08;
            mask-image: linear-gradient(to right, transparent, #000 70%);
            pointer-events: none;
        }
    }
}

/* Wide: the full row, as in the reference. */
@container pk-list (min-width: 1240px) {
    .pk-item.is-list {
        grid-template-columns: 236px minmax(0, 1fr) auto auto auto auto auto;
        grid-template-rows: auto;
        grid-template-areas: 'media main price booking status updated actions';

        .pk-booking,
        .pk-status,
        .pk-updated {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-self: stretch;
            border-left: 1px solid var(--color-line);
            padding: 0.75rem 1.25rem;
        }

        .pk-updated {
            flex-direction: row;
            align-items: center;
        }

        .pk-booking .pk-label,
        .pk-status .pk-label {
            display: block;
        }

        .pk-pill {
            margin-top: 0.4rem;
        }

        .pk-updated-inline {
            display: none;
        }
    }
}

@keyframes pk-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pk-item {
        animation: none;
    }

    .pk-item,
    .pk-photo,
    .pk-open {
        transition: none;
    }

    .pk-item:hover,
    .pk-item:hover .pk-photo {
        transform: none;
    }
}
</style>
