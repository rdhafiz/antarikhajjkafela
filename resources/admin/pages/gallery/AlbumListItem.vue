<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { CalendarDays, ChevronRight, CircleCheck, EllipsisVertical, ExternalLink, Eye, EyeOff, Image as ImageIcon, Images, Link2, Pencil, Trash2, Video } from 'lucide-vue-next';
import { formatDate } from '@admin/lib/format';

export interface AlbumItem {
    id: number;
    title: string;
    description: string;
    slug: string;
    url: string;
    event_date: string | null;
    is_published: boolean;
    cover_thumb: string | null;
    images_count: number;
    videos_count: number;
}

/**
 * One album: a photo card (grid view, phones) or a wide row (list view, when the list has room).
 */
const props = defineProps<{ album: AlbumItem; layout: 'list' | 'grid'; index: number; canUpdate: boolean; canDelete: boolean }>();
const emit = defineEmits<{ open: []; toggle: []; remove: [] }>();

const router = useRouter();

const plural = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;
const empty = computed(() => props.album.images_count + props.album.videos_count === 0);
// Albums without photos or videos are not shown on the website, even when published.
const status = computed(() =>
    !props.album.is_published
        ? { label: 'Hidden', icon: EyeOff, cls: 'is-slate' }
        : empty.value
          ? { label: 'Empty', icon: Images, cls: 'is-amber' }
          : { label: 'Published', icon: CircleCheck, cls: 'is-green' },
);
</script>

<template>
    <article class="al-item group" :class="[layout === 'list' ? 'is-list' : 'is-grid', { 'is-hidden': !album.is_published }]" :style="{ '--i': index }" @click="emit('open')">
        <div class="al-media">
            <img v-if="album.cover_thumb" :src="album.cover_thumb" alt="" class="al-photo" loading="lazy" />
            <div v-else class="al-photo al-photo-empty"><Images class="size-8" aria-hidden="true" /></div>
            <span class="al-counts" aria-hidden="true">
                <span><ImageIcon class="size-3.5" />{{ album.images_count }}</span>
                <span><Video class="size-3.5" />{{ album.videos_count }}</span>
            </span>
        </div>

        <div class="al-main">
            <p v-if="album.event_date" class="al-date"><CalendarDays class="size-3.5 shrink-0" aria-hidden="true" />{{ formatDate(album.event_date) }}</p>
            <h2 class="al-title">{{ album.title }}</h2>
            <p class="al-slug"><Link2 class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">/gallery/{{ album.slug }}</span></p>
            <p v-if="album.description" class="al-desc">{{ album.description }}</p>
            <p class="al-tags">
                <span class="al-tag" style="--chip: #38bdf8"><ImageIcon class="size-3.5 shrink-0" aria-hidden="true" />{{ plural(album.images_count, 'photo', 'photos') }}</span>
                <span class="al-tag" style="--chip: #fb7185"><Video class="size-3.5 shrink-0" aria-hidden="true" />{{ plural(album.videos_count, 'video', 'videos') }}</span>
            </p>
        </div>

        <div class="al-cell al-status">
            <span class="al-label">Website</span>
            <span class="al-pill" :class="status.cls"><component :is="status.icon" class="size-4" aria-hidden="true" />{{ status.label }}</span>
        </div>

        <div class="al-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="al-icon-btn" :aria-label="`More actions for ${album.title}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="router.push({ name: 'album-editor', params: { id: album.id } })">
                            <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit album and photos
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="album.is_published && !empty" as="a" :href="album.url" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="canUpdate" class="lm-item" @select="emit('toggle')">
                            <span class="lm-icon" style="--tone: #34d399"><component :is="album.is_published ? EyeOff : Eye" class="size-4" /></span>{{ album.is_published ? 'Hide from the website' : 'Show on the website' }}
                        </DropdownMenuItem>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete album
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="al-icon-btn al-open" :aria-label="`Open ${album.title}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Card (grid view, and every view when the list is narrow). */
.al-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: auto 1fr auto;
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
    animation: al-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 12) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-hidden .al-photo {
        filter: grayscale(0.85);
        opacity: 0.75;
    }
}

.al-media {
    grid-area: media;
    position: relative;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: var(--color-canvas);
}

.al-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1), filter 300ms ease;
}

.al-item:hover .al-photo {
    transform: scale(1.05);
}

.al-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f766e;
    background: linear-gradient(135deg, #ccfbf1, #f0fdfa);
}

/* Photo and video counts on the picture, like a phone gallery. */
.al-counts {
    position: absolute;
    right: 0.65rem;
    bottom: 0.65rem;
    display: flex;
    gap: 0.35rem;

    > span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border-radius: 999px;
        background: rgb(11 29 51 / 0.72);
        padding: 0.2rem 0.55rem;
        font-size: 12px;
        font-weight: 700;
        color: #fff;
        backdrop-filter: blur(4px);
    }
}

.al-main {
    grid-area: main;
    align-self: start;
    min-width: 0;
    padding: 1rem 1.1rem 0;
}

.al-date {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 12.5px;
    font-weight: 600;
    color: #0f766e;
}

.al-title {
    margin-top: 0.3rem;
    font-size: 16.5px;
    font-weight: 700;
    line-height: 1.35;
    color: var(--color-ink);
    overflow-wrap: anywhere;
}

.al-slug {
    margin-top: 0.15rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 12.5px;
    color: var(--color-ink-soft);
}

.al-desc {
    margin-top: 0.4rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 13.5px;
    line-height: 1.55;
    color: var(--color-ink-soft);
}

.al-tags {
    display: none;
    margin-top: 0.6rem;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.al-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    background: color-mix(in oklab, var(--chip) 14%, white);
    color: color-mix(in oklab, var(--chip) 42%, black);
}

.al-cell {
    min-width: 0;
    padding: 0.85rem 1.1rem 1rem;
}

.al-label {
    display: none;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.al-status {
    grid-area: status;
}

.al-pill {
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
    &.is-slate {
        background: #eef1f5;
        color: #475569;
    }
}

.al-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.6rem 0.9rem 0.85rem 0;
    cursor: default;
}

.al-icon-btn {
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

.al-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .al-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* List view with room: one row. */
@container al-list (min-width: 760px) {
    .al-item.is-list {
        grid-template-columns: 13rem minmax(0, 1fr) auto auto;
        grid-template-rows: auto;
        grid-template-areas: 'media main status actions';
        min-height: 140px;

        .al-media {
            align-self: stretch;
            aspect-ratio: auto;
        }

        .al-counts {
            display: none;
        }

        .al-main {
            align-self: center;
            padding: 1rem 1.25rem;
        }

        .al-tags {
            display: flex;
        }

        .al-status {
            width: 10rem;
            align-self: stretch;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            border-left: 1px solid var(--color-line);
            padding: 0.75rem 1.25rem;
        }

        .al-label {
            display: block;
            margin-bottom: 0.4rem;
        }

        .al-actions {
            padding: 0 1rem 0 0.5rem;
        }
    }
}

@keyframes al-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .al-item {
        animation: none;
    }

    .al-item,
    .al-photo,
    .al-open {
        transition: none;
    }

    .al-item:hover,
    .al-item:hover .al-photo {
        transform: none;
    }
}
</style>
