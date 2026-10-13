<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import {
    CalendarClock,
    CalendarDays,
    CalendarX,
    ChevronRight,
    CircleCheck,
    Copy,
    EllipsisVertical,
    ExternalLink,
    Eye,
    FileText,
    House,
    Layers,
    Link2,
    Lock,
    Megaphone,
    PencilLine,
    Pencil,
    Radio,
    Trash2,
} from 'lucide-vue-next';
import { formatDate } from '@admin/lib/format';
import type { PageSummary } from './builder/types';

/**
 * One page or campaign: a wide row when the list has room (container queries), a card otherwise.
 */
const props = defineProps<{
    page: PageSummary;
    title: string;
    index: number;
    canCreate: boolean;
    canDelete: boolean;
}>();
const emit = defineEmits<{ open: []; preview: []; duplicate: []; remove: [] }>();

const router = useRouter();

const address = computed(() => (props.page.system_key === 'home' ? '/' : `/${props.page.slug}`));
const isOnline = computed(() => props.page.state === 'published' || props.page.state === 'live');
const builtIn = computed(() => props.page.type === 'default');
const kind = computed(() => (props.page.type === 'campaign' ? 'campaign' : 'page'));

const state = computed(
    () =>
        ({
            draft: { label: 'Draft', icon: PencilLine, cls: 'is-slate' },
            published: { label: 'Published', icon: CircleCheck, cls: 'is-green' },
            live: { label: 'Live', icon: Radio, cls: 'is-green' },
            scheduled: { label: 'Scheduled', icon: CalendarClock, cls: 'is-amber' },
            expired: { label: 'Expired', icon: CalendarX, cls: 'is-rose' },
        })[props.page.state],
);

/** When a campaign runs, e.g. "12 Oct – 30 Nov 2026" or "Now – no end". */
const runs = computed(() => {
    if (props.page.type !== 'campaign') return null;
    if (!props.page.starts_at && !props.page.expires_at) return 'Runs with no end date';
    return `${props.page.starts_at ? formatDate(props.page.starts_at) : 'Now'} – ${props.page.expires_at ? formatDate(props.page.expires_at) : 'no end'}`;
});
const sections = computed(() => `${props.page.sections_count} ${props.page.sections_count === 1 ? 'section' : 'sections'}`);
</script>

<template>
    <article class="pg-item group" :style="{ '--i': index }" @click="emit('open')">
        <div class="pg-media">
            <img v-if="page.cover_url" :src="page.cover_url" alt="" class="pg-photo" loading="lazy" />
            <div v-else class="pg-photo pg-photo-empty"><component :is="page.type === 'campaign' ? Megaphone : FileText" class="size-7" aria-hidden="true" /></div>
            <span v-if="page.system_key === 'home'" class="pg-flag" title="Home page"><House class="size-4" aria-hidden="true" /><span class="sr-only">Home page</span></span>
        </div>

        <div class="pg-main">
            <h2 class="pg-name">
                <span class="truncate">{{ title }}</span>
                <span v-if="builtIn" class="pg-lock" title="Built-in page: it can be edited but not deleted"><Lock class="size-3" aria-hidden="true" />Built-in</span>
            </h2>
            <p class="pg-address"><Link2 class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ address }}</span></p>
            <p class="pg-tags">
                <span class="pg-tag" style="--chip: #a78bfa"><Layers class="size-3.5 shrink-0" aria-hidden="true" />{{ sections }}</span>
                <span v-if="runs" class="pg-tag" style="--chip: #38bdf8"><CalendarClock class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ runs }}</span></span>
                <span class="pg-tag pg-updated-inline" style="--chip: #94a3b8"><CalendarDays class="size-3.5 shrink-0" aria-hidden="true" />Updated {{ page.updated_at ? formatDate(page.updated_at) : '—' }}</span>
            </p>
        </div>

        <div class="pg-cell pg-status">
            <span class="pg-label">Status</span>
            <span class="pg-pill" :class="state.cls"><component :is="state.icon" class="size-4" aria-hidden="true" />{{ state.label }}</span>
        </div>

        <div class="pg-cell pg-updated">
            <CalendarDays class="size-5 shrink-0 text-ink-soft" aria-hidden="true" />
            <span>
                <span class="pg-label">Updated</span>
                <span class="block text-[14px] font-medium whitespace-nowrap text-ink">{{ page.updated_at ? formatDate(page.updated_at) : '—' }}</span>
            </span>
        </div>

        <div class="pg-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="pg-icon-btn" :aria-label="`More actions for ${title}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="router.push({ name: 'page-editor', params: { id: page.id } })">
                            <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit {{ kind }}
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="isOnline" as="a" :href="page.url" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                        </DropdownMenuItem>
                        <DropdownMenuItem class="lm-item" @select="emit('preview')">
                            <span class="lm-icon" style="--tone: #a78bfa"><Eye class="size-4" /></span>Preview the saved version
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="!builtIn && canCreate" class="lm-item" @select="emit('duplicate')">
                            <span class="lm-icon" style="--tone: #2dd4bf"><Copy class="size-4" /></span>Duplicate as a draft
                        </DropdownMenuItem>
                        <template v-if="!builtIn && canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete {{ kind }}
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="pg-icon-btn pg-open" :aria-label="`Open ${title}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Narrow (default): a card with the picture on top. */
.pg-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'media media'
        'main main'
        'status actions';
    align-items: center;
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: pg-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }
}

.pg-media {
    grid-area: media;
    position: relative;
    aspect-ratio: 16 / 7;
    overflow: hidden;
    background: var(--color-canvas);
}

.pg-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1);
}

.pg-item:hover .pg-photo {
    transform: scale(1.05);
}

.pg-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4f46e5;
    background: linear-gradient(135deg, #e0e7ff, #f5f3ff);
}

.pg-flag {
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
    color: #4f46e5;
    box-shadow: 0 4px 12px rgb(15 23 42 / 0.18);
}

.pg-main {
    grid-area: main;
    min-width: 0;
    padding: 1rem 1.1rem 0;
}

.pg-name {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 16.5px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--color-ink);
}

.pg-lock {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    background: #eef2ff;
    padding: 0.15rem 0.5rem;
    font-size: 11.5px;
    font-weight: 600;
    color: #4338ca;
}

.pg-address {
    margin-top: 0.15rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 13px;
    color: var(--color-ink-soft);
}

.pg-tags {
    margin-top: 0.6rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.pg-tag {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.25rem 0.65rem;
    font-size: 12.5px;
    font-weight: 600;
    white-space: nowrap;
    background: color-mix(in oklab, var(--chip) 14%, white);
    color: color-mix(in oklab, var(--chip) 42%, black);
}

.pg-cell {
    min-width: 0;
    padding: 0.75rem 1.1rem 1rem;
}

.pg-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.pg-status {
    grid-area: status;
}

.pg-status .pg-label,
.pg-updated {
    display: none;
}

.pg-pill {
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

.pg-updated {
    grid-area: updated;
    align-items: center;
    gap: 0.65rem;
}

.pg-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.5rem 0.9rem 0.75rem 0;
    cursor: default;
}

.pg-icon-btn {
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

.pg-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .pg-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* Medium: one row; the update date stays in the tags. */
@container pg-list (min-width: 760px) {
    .pg-item {
        grid-template-columns: 11rem minmax(0, 1fr) auto auto;
        grid-template-areas: 'media main status actions';
        min-height: 118px;
    }

    .pg-media {
        align-self: stretch;
        aspect-ratio: auto;
    }

    .pg-main {
        padding: 1rem 1.25rem;
    }

    .pg-status {
        width: 10rem;
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .pg-status .pg-label {
        display: block;
        margin-bottom: 0.4rem;
    }

    .pg-actions {
        padding: 0 1rem 0 0.5rem;
    }
}

/* Wide: the update date gets its own column. */
@container pg-list (min-width: 1100px) {
    .pg-item {
        grid-template-columns: 13rem minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'media main status updated actions';
    }

    .pg-updated {
        width: 11rem;
        display: flex;
        align-self: stretch;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .pg-updated-inline {
        display: none;
    }
}

@keyframes pg-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pg-item {
        animation: none;
    }

    .pg-item,
    .pg-photo,
    .pg-open {
        transition: none;
    }

    .pg-item:hover,
    .pg-item:hover .pg-photo {
        transform: none;
    }
}
</style>
