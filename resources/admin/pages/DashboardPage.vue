<script setup lang="ts">
import { computed, onMounted, ref, type Component } from 'vue';
import { ArrowRight, ArrowUpRight, CalendarCheck, CalendarPlus, ExternalLink, GalleryHorizontalEnd, Hourglass, Inbox, Newspaper, Package, Users, Wallet } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import { navigation } from '@admin/navigation';
import { http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { money, statusLabels, statusTone, type BookingStatus } from '@admin/pages/bookings/types';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';

interface Dashboard {
    bookings?: {
        pending: number;
        this_month: number;
        travelers_this_month: number;
        collected_this_month: number;
        trend: { month: string; count: number }[];
        recent: { id: number; reference: string; status: BookingStatus; customer_name: string; package: string | null; total: number; is_unread: boolean; created_at: string }[];
    };
    inquiries?: {
        new: number;
        recent: { id: number; name: string; phone: string | null; source: string; status: string; is_unread: boolean; created_at: string }[];
    };
    packages?: { published: number };
    posts?: { published: number };
    gallery?: { albums: number };
}

interface Stat {
    label: string;
    value: string;
    hint: string;
    icon: Component;
    tone: string;
    to: string;
}

const auth = useAuthStore();
const app = useAppStore();

const data = ref<Dashboard | null>(null);
const failed = ref(false);

const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
});
const today = new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

const statusLabel: Record<BookingStatus, string> = statusLabels;
const count = new Intl.NumberFormat('en-IN');

const stats = computed<Stat[]>(() => {
    const d = data.value;
    if (!d) return [];
    const list: Stat[] = [];
    if (d.bookings) {
        list.push(
            { label: 'Waiting for review', value: count.format(d.bookings.pending), hint: 'Pending bookings', icon: Hourglass, tone: '#fbbf24', to: '/admin/bookings?status=pending' },
            { label: 'Bookings this month', value: count.format(d.bookings.this_month), hint: `${count.format(d.bookings.travelers_this_month)} travellers`, icon: CalendarPlus, tone: '#34d399', to: '/admin/bookings' },
            { label: 'Collected this month', value: money(d.bookings.collected_this_month), hint: 'Paid payments', icon: Wallet, tone: '#a78bfa', to: '/admin/bookings' },
        );
    }
    if (d.inquiries) list.push({ label: 'Inquiries to handle', value: count.format(d.inquiries.new), hint: 'Still marked New', icon: Inbox, tone: '#38bdf8', to: '/admin/inquiries?status=new' });
    if (d.packages) list.push({ label: 'Live packages', value: count.format(d.packages.published), hint: 'Published', icon: Package, tone: '#fb923c', to: '/admin/packages?status=published' });
    if (d.posts) list.push({ label: 'Live posts', value: count.format(d.posts.published), hint: 'Blog & news', icon: Newspaper, tone: '#fb7185', to: '/admin/blog' });
    if (d.gallery) list.push({ label: 'Gallery albums', value: count.format(d.gallery.albums), hint: 'Photos & videos', icon: GalleryHorizontalEnd, tone: '#2dd4bf', to: '/admin/gallery' });
    return list.slice(0, 4);
});

const trend = computed(() => {
    const rows = data.value?.bookings?.trend ?? [];
    const max = Math.max(1, ...rows.map((r) => r.count));
    const label = new Intl.DateTimeFormat('en-GB', { month: 'short' });
    return rows.map((r) => ({ ...r, label: label.format(new Date(`${r.month}-01T00:00:00`)), height: Math.max(4, Math.round((r.count / max) * 100)) }));
});
const trendTotal = computed(() => trend.value.reduce((sum, r) => sum + r.count, 0));

const shortcuts = computed(() =>
    navigation
        .flatMap((section) => section.items)
        .filter((item) => item.to !== '/admin' && (!item.permission || auth.can(item.permission)))
        .slice(0, 8),
);

const sourceLabel = (source: string) => source.replace(/[_-]/g, ' ').replace(/^./, (c) => c.toUpperCase());

onMounted(async () => {
    try {
        data.value = (await http.get<Dashboard>('/dashboard')).data;
    } catch {
        failed.value = true;
    }
});
</script>

<template>
    <div>
        <section class="db-hero relative overflow-hidden rounded-[var(--radius-card)] px-6 py-7 text-white sm:px-8 sm:py-8">
            <div class="db-glow absolute -top-28 -right-20 size-80 rounded-full" aria-hidden="true" />
            <div class="db-grid absolute inset-0" aria-hidden="true" />
            <div class="relative flex flex-wrap items-end justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-[12.5px] font-semibold tracking-[0.14em] text-gold uppercase">{{ greeting }} · {{ today }}</p>
                    <h1 class="mt-2 text-[26px] leading-tight font-bold tracking-[-0.01em] break-words sm:text-[30px]">{{ auth.user?.name }}</h1>
                    <p class="mt-2 max-w-xl text-[14px] text-white/70">Here's what is happening at {{ app.name }} today.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink v-if="auth.can('bookings.view')" to="/admin/bookings" class="db-hero-btn is-primary"><CalendarCheck class="size-4" />Bookings</RouterLink>
                    <a :href="app.siteUrl" target="_blank" rel="noopener" class="db-hero-btn"><ExternalLink class="size-4" />Visit website</a>
                </div>
            </div>
        </section>

        <div v-if="stats.length" class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <RouterLink v-for="stat in stats" :key="stat.label" :to="stat.to" class="card db-stat group flex items-start gap-4 p-5" :style="{ '--tone': stat.tone }">
                <span class="ph-tile flex size-12 shrink-0 items-center justify-center rounded-2xl" aria-hidden="true"><component :is="stat.icon" class="size-[22px]" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-semibold text-ink-muted">{{ stat.label }}</span>
                    <span class="mt-0.5 block truncate text-[26px] leading-tight font-bold tracking-[-0.02em] text-ink">{{ stat.value }}</span>
                    <span class="mt-0.5 block text-[12.5px] text-ink-muted">{{ stat.hint }}</span>
                </span>
                <ArrowUpRight class="size-4 shrink-0 text-ink-muted transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" aria-hidden="true" />
            </RouterLink>
        </div>
        <div v-else-if="!data && !failed" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-hidden="true">
            <div v-for="n in 4" :key="n" class="card h-[104px] animate-pulse bg-canvas" />
        </div>

        <div v-if="data?.bookings || data?.inquiries" class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-5">
            <section v-if="data?.bookings" class="card p-5 sm:p-6 xl:col-span-2" style="--tone: #34d399">
                <header class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[15px] font-bold text-ink">Bookings, last 6 months</h2>
                        <p class="mt-0.5 text-[13px] text-ink-muted">{{ count.format(trendTotal) }} in total</p>
                    </div>
                    <span class="ph-tile flex size-10 items-center justify-center rounded-xl" aria-hidden="true"><CalendarCheck class="size-5" /></span>
                </header>
                <ol class="db-chart mt-6 grid h-48 grid-cols-6 items-end gap-2.5 sm:gap-4" aria-label="Bookings per month">
                    <li v-for="(bar, i) in trend" :key="bar.month" class="flex h-full flex-col items-center justify-end gap-2" :aria-label="`${bar.label}: ${bar.count}`">
                        <span class="text-[12px] font-bold text-ink">{{ bar.count }}</span>
                        <span class="db-bar w-full max-w-11 rounded-t-lg" :class="{ 'is-current': i === trend.length - 1 }" :style="{ height: `${bar.height}%`, '--i': i }" />
                        <span class="text-[12px] font-medium text-ink-muted">{{ bar.label }}</span>
                    </li>
                </ol>
            </section>

            <section v-if="data?.bookings" class="card overflow-hidden xl:col-span-3">
                <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <h2 class="text-[15px] font-bold text-ink">Latest bookings</h2>
                    <RouterLink to="/admin/bookings" class="inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline">All bookings<ArrowRight class="size-3.5" /></RouterLink>
                </header>
                <p v-if="!data.bookings.recent.length" class="px-6 py-10 text-center text-[14px] text-ink-muted">No bookings yet.</p>
                <ul v-else class="divide-y divide-line">
                    <li v-for="booking in data.bookings.recent" :key="booking.id">
                        <RouterLink :to="{ name: 'booking', params: { id: booking.id } }" class="flex items-center gap-3.5 px-5 py-3.5 transition-colors hover:bg-canvas sm:px-6">
                            <span class="db-avatar flex size-10 shrink-0 items-center justify-center rounded-full text-[14px] font-bold" aria-hidden="true">{{ booking.customer_name.charAt(0).toUpperCase() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 text-[14px] font-semibold text-ink">
                                    <span class="truncate">{{ booking.customer_name }}</span>
                                    <span v-if="booking.is_unread" class="size-2 shrink-0 rounded-full bg-sky-500" aria-label="New" />
                                </span>
                                <span class="block truncate text-[12.5px] text-ink-muted">{{ booking.reference }} · {{ booking.package ?? 'Package' }}</span>
                            </span>
                            <span class="hidden text-right sm:block">
                                <span class="block text-[14px] font-semibold text-ink">{{ money(booking.total) }}</span>
                                <span class="block text-[12px] text-ink-muted">{{ formatDate(booking.created_at) }}</span>
                            </span>
                            <AppBadge :tone="statusTone[booking.status]">{{ statusLabel[booking.status] }}</AppBadge>
                        </RouterLink>
                    </li>
                </ul>
            </section>

            <section v-if="data?.inquiries" class="card overflow-hidden" :class="data?.bookings ? 'xl:col-span-5' : 'xl:col-span-5'">
                <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <h2 class="text-[15px] font-bold text-ink">Latest inquiries</h2>
                    <RouterLink to="/admin/inquiries" class="inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline">All inquiries<ArrowRight class="size-3.5" /></RouterLink>
                </header>
                <p v-if="!data.inquiries.recent.length" class="px-6 py-10 text-center text-[14px] text-ink-muted">No inquiries yet.</p>
                <ul v-else class="grid grid-cols-[minmax(0,1fr)] md:grid-cols-2 xl:grid-cols-3">
                    <li v-for="inquiry in data.inquiries.recent" :key="inquiry.id" class="border-b border-line md:border-r">
                        <RouterLink to="/admin/inquiries" class="flex items-center gap-3.5 px-5 py-3.5 transition-colors hover:bg-canvas sm:px-6" style="--tone: #38bdf8">
                            <span class="ph-tile flex size-10 shrink-0 items-center justify-center rounded-full" aria-hidden="true"><Users class="size-[18px]" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 text-[14px] font-semibold text-ink">
                                    <span class="truncate">{{ inquiry.name }}</span>
                                    <span v-if="inquiry.is_unread" class="size-2 shrink-0 rounded-full bg-sky-500" aria-label="Unread" />
                                </span>
                                <span class="block truncate text-[12.5px] text-ink-muted">{{ sourceLabel(inquiry.source) }} · {{ formatDate(inquiry.created_at) }}</span>
                            </span>
                        </RouterLink>
                    </li>
                </ul>
            </section>
        </div>

        <section v-if="shortcuts.length" class="mt-8">
            <h2 class="mb-3 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Quick access</h2>
            <div class="grid grid-cols-[minmax(0,1fr)] gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <RouterLink
                    v-for="item in shortcuts"
                    :key="item.to"
                    :to="item.to"
                    class="card group flex items-center gap-3.5 p-4 transition-[border-color,box-shadow,transform] hover:-translate-y-0.5 hover:shadow-[var(--shadow-pop)]"
                    :style="{ '--tone': item.tone }"
                >
                    <span class="ph-tile flex size-10 shrink-0 items-center justify-center rounded-xl" aria-hidden="true"><component :is="item.icon" class="size-5" /></span>
                    <span class="min-w-0 flex-1 truncate text-[14.5px] font-semibold text-ink">{{ item.label }}</span>
                    <ArrowRight class="size-4 text-ink-muted transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </RouterLink>
            </div>
        </section>
    </div>
</template>

<style scoped>
.db-hero {
    background: linear-gradient(135deg, var(--admin-sidebar-bg, #0b2545) 0%, color-mix(in oklab, var(--admin-primary, #0b2545) 80%, #000) 100%);
}

.db-glow {
    background: radial-gradient(circle, color-mix(in oklab, var(--color-gold, #c9a227) 32%, transparent), transparent 70%);
}

.db-grid {
    background-image: radial-gradient(rgb(255 255 255 / 0.08) 1px, transparent 1px);
    background-size: 18px 18px;
    mask-image: linear-gradient(to left, #000, transparent 70%);
}

.db-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.75rem;
    padding: 0.6rem 0.95rem;
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    background: rgb(255 255 255 / 0.1);
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.18);
    transition: background-color 200ms ease;

    &:hover {
        background: rgb(255 255 255 / 0.18);
    }

    &.is-primary {
        background: var(--color-gold, #c9a227);
        color: #1a1405;
        box-shadow: none;
    }
}

.db-stat {
    transition: box-shadow 220ms ease, transform 220ms ease;

    &:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-pop);
    }
}

.db-bar {
    background: linear-gradient(to top, color-mix(in oklab, var(--tone) 55%, white), color-mix(in oklab, var(--tone) 30%, white));
    transform-origin: bottom;
    animation: db-grow 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--i) * 70ms + 200ms);

    &.is-current {
        background: linear-gradient(to top, color-mix(in oklab, var(--tone) 80%, black), var(--tone));
    }
}

.db-avatar {
    background: color-mix(in oklab, #34d399 16%, white);
    color: color-mix(in oklab, #34d399 45%, black);
}

@keyframes db-grow {
    from {
        transform: scaleY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .db-bar {
        animation: none;
    }
}
</style>
