<script setup lang="ts">
import { computed } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { CalendarDays, ChevronRight, CircleAlert, Copy, EllipsisVertical, ExternalLink, Info, Maximize2, Trash2 } from 'lucide-vue-next';
import { formatBytes, formatDate } from '@admin/lib/format';
import type { Media } from '@admin/types';

/**
 * One image: a thumbnail card (grid view, phones) or a wide row (list view, when the list has room).
 */
const props = defineProps<{ media: Media; usageLabel: string; usageTone: string; layout: 'list' | 'grid'; index: number; canDelete: boolean; locale: string }>();
const emit = defineEmits<{ open: []; copy: []; remove: [] }>();

/** "Social share (Open Graph)" → "Social share", so the badge stays on one line. */
const shortUsage = computed(() => props.usageLabel.replace(/\s*\(.*\)\s*$/, '').split(' / ')[0]);
const format = computed(() => props.media.mime_type.split('/')[1]?.replace('svg+xml', 'svg').replace('jpeg', 'jpg').toUpperCase() ?? '');
const hasAlt = computed(() => Boolean(props.media.alt?.[props.locale]?.trim() || props.media.alt?.en?.trim()));
/** How much smaller the largest web version is than the uploaded file. */
const saved = computed(() => {
    const largest = Object.values(props.media.variants ?? {}).reduce((max, v) => Math.max(max, v.size), 0);
    return props.media.size > 0 && largest > 0 ? Math.max(0, Math.round((1 - largest / props.media.size) * 100)) : 0;
});
</script>

<template>
    <article class="md-item group" :class="layout === 'list' ? 'is-list' : 'is-grid'" :style="{ '--i': index, '--use': usageTone }" @click="emit('open')">
        <div class="md-media">
            <img :src="media.thumb_url ?? ''" :alt="media.alt?.[locale] || media.alt?.en || ''" class="md-photo" loading="lazy" />
            <span class="md-use" :title="usageLabel">{{ shortUsage }}</span>
            <span v-if="!hasAlt" class="md-noalt" title="No alt text: add a short description for screen readers and search engines">
                <CircleAlert class="size-3.5" aria-hidden="true" /><span class="md-noalt-text">No alt text</span>
            </span>
        </div>

        <div class="md-main">
            <h2 class="md-name" :title="media.original_name">{{ media.original_name }}</h2>
            <p class="md-meta">
                <span class="inline-flex items-center gap-1"><Maximize2 class="size-3.5" aria-hidden="true" />{{ media.width }} × {{ media.height }}</span>
                <span>{{ format }}</span>
                <span title="Size of the uploaded file">{{ formatBytes(media.size) }}</span>
            </p>
            <p class="md-extra">
                <span v-if="saved" class="md-saved">{{ saved }}% smaller on the website</span>
                <span v-if="media.created_at" class="inline-flex items-center gap-1 text-ink-soft"><CalendarDays class="size-3.5" aria-hidden="true" />{{ formatDate(media.created_at) }}</span>
            </p>
        </div>

        <div class="md-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="md-icon-btn" :aria-label="`More actions for ${media.original_name}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="emit('open')">
                            <span class="lm-icon" style="--tone: #fbbf24"><Info class="size-4" /></span>Details and alt text
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="media.url" as="a" :href="media.url" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>Open full size
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="media.url" class="lm-item" @select="emit('copy')">
                            <span class="lm-icon" style="--tone: #a78bfa"><Copy class="size-4" /></span>Copy image link
                        </DropdownMenuItem>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete image
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="md-icon-btn md-open" :aria-label="`Open ${media.original_name}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Card (grid view, and every view when the list is narrow). */
.md-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: auto 1fr;
    grid-template-areas:
        'media media'
        'main actions';
    align-items: end;
    overflow: hidden;
    border-radius: 1.05rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: md-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 16) * 25ms);
    transition: transform 240ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 240ms ease, border-color 240ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 16px 34px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }
}

.md-media {
    grid-area: media;
    position: relative;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background-color: #fff;
}

.md-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 500ms cubic-bezier(0.22, 1, 0.36, 1);
}

.md-item:hover .md-photo {
    transform: scale(1.05);
}

.md-use {
    position: absolute;
    top: 0.55rem;
    left: 0.55rem;
    border-radius: 999px;
    background: color-mix(in oklab, var(--use) 22%, white);
    max-width: calc(100% - 1.1rem);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0.15rem 0.55rem;
    font-size: 11.5px;
    font-weight: 700;
    color: color-mix(in oklab, var(--use) 35%, black);
    box-shadow: 0 2px 8px rgb(15 23 42 / 0.15);
}

.md-noalt {
    position: absolute;
    right: 0.55rem;
    bottom: 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    background: #fffbeb;
    padding: 0.15rem 0.5rem;
    font-size: 11.5px;
    font-weight: 700;
    color: #92400e;
    box-shadow: 0 2px 8px rgb(15 23 42 / 0.15);
}

.md-main {
    grid-area: main;
    align-self: start;
    min-width: 0;
    padding: 0.75rem 0.25rem 0.85rem 0.9rem;
}

.md-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--color-ink);
}

.md-meta {
    margin-top: 0.2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.15rem 0.6rem;
    font-size: 12px;
    color: var(--color-ink-soft);
}

.md-extra {
    display: none;
    margin-top: 0.45rem;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
    font-size: 12px;
}

.md-saved {
    border-radius: 999px;
    background: #e8f8ef;
    padding: 0.1rem 0.55rem;
    font-weight: 600;
    color: #157347;
}

.md-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    padding: 0 0.4rem 0.6rem 0;
    cursor: default;
}

.md-icon-btn {
    display: flex;
    width: 2.5rem;
    height: 2.5rem;
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

/* Cards are small; the open arrow is only on rows (the card itself opens). */
.md-open {
    display: none;
}

/* List view with room: one row. */
@container md-list (min-width: 700px) {
    .md-item.is-list {
        grid-template-columns: 9rem minmax(0, 1fr) auto;
        grid-template-rows: auto;
        grid-template-areas: 'media main actions';
        align-items: center;

        .md-media {
            align-self: stretch;
            aspect-ratio: auto;
            min-height: 96px;
        }

        .md-noalt-text {
            display: none;
        }

        .md-main {
            align-self: center;
            padding: 0.8rem 1.25rem;
        }

        .md-name {
            font-size: 14.5px;
        }

        .md-extra {
            display: flex;
        }

        .md-actions {
            gap: 0.35rem;
            padding: 0 1rem 0 0.5rem;
        }

        .md-open {
            display: flex;
            background: #fff;
            box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);
        }
    }
}

@keyframes md-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .md-item {
        animation: none;
    }

    .md-item,
    .md-photo {
        transition: none;
    }

    .md-item:hover,
    .md-item:hover .md-photo {
        transform: none;
    }
}
</style>
