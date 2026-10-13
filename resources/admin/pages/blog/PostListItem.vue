<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { CalendarClock, CalendarDays, ChevronRight, CircleCheck, EllipsisVertical, ExternalLink, Eye, FolderTree, Hash, Newspaper, Pencil, PencilLine, Star, Trash2, UserRound } from 'lucide-vue-next';
import { formatDate } from '@admin/lib/format';
import type { PostState } from './types';

export interface PostItem {
    id: number;
    title: string;
    excerpt: string;
    slug: string;
    url: string;
    category: string | null;
    author: string;
    tags: string[];
    state: PostState;
    is_featured: boolean;
    banner_url: string | null;
    published_at: string | null;
}

/**
 * One post: a wide row (list view, when the list has room) or a magazine card (grid view, phones).
 */
const props = defineProps<{ post: PostItem; layout: 'list' | 'grid'; index: number; canDelete: boolean }>();
const emit = defineEmits<{ open: []; preview: []; remove: [] }>();

const router = useRouter();

const state = computed(
    () =>
        ({
            published: { label: 'Published', icon: CircleCheck, cls: 'is-green' },
            scheduled: { label: 'Scheduled', icon: CalendarClock, cls: 'is-amber' },
            draft: { label: 'Draft', icon: PencilLine, cls: 'is-slate' },
        })[props.post.state],
);
const date = computed(() => (props.post.published_at ? formatDate(props.post.published_at, props.post.state === 'scheduled') : 'Not published'));
const dateLabel = computed(() => (props.post.state === 'scheduled' ? 'Goes live' : 'Published'));
</script>

<template>
    <article class="po-item group" :class="layout === 'list' ? 'is-list' : 'is-grid'" :style="{ '--i': index }" @click="emit('open')">
        <div class="po-media">
            <img v-if="post.banner_url" :src="post.banner_url" alt="" class="po-photo" loading="lazy" />
            <div v-else class="po-photo po-photo-empty"><Newspaper class="size-7" aria-hidden="true" /></div>
            <span v-if="post.is_featured" class="po-star" title="Featured"><Star class="size-4 fill-current" aria-hidden="true" /><span class="sr-only">Featured</span></span>
        </div>

        <div class="po-main">
            <p class="po-kicker">
                <span class="po-category"><FolderTree class="size-3.5 shrink-0" aria-hidden="true" />{{ post.category ?? 'No category' }}</span>
                <span class="po-date-inline"><CalendarDays class="size-3.5 shrink-0" aria-hidden="true" />{{ date }}</span>
            </p>
            <h2 class="po-title">{{ post.title }}</h2>
            <p v-if="post.excerpt" class="po-excerpt">{{ post.excerpt }}</p>
            <p class="po-tags">
                <span v-if="post.author" class="po-tag" style="--chip: #38bdf8"><UserRound class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ post.author }}</span></span>
                <span v-for="tag in post.tags.slice(0, 3)" :key="tag" class="po-tag" style="--chip: #2dd4bf"><Hash class="size-3.5 shrink-0" aria-hidden="true" />{{ tag }}</span>
            </p>
        </div>

        <div class="po-cell po-status">
            <span class="po-label">Status</span>
            <span class="po-pill" :class="state.cls"><component :is="state.icon" class="size-4" aria-hidden="true" />{{ state.label }}</span>
        </div>

        <div class="po-cell po-date">
            <CalendarDays class="size-5 shrink-0 text-ink-soft" aria-hidden="true" />
            <span>
                <span class="po-label">{{ dateLabel }}</span>
                <span class="block text-[14px] font-medium whitespace-nowrap text-ink">{{ date }}</span>
            </span>
        </div>

        <div class="po-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="po-icon-btn" :aria-label="`More actions for ${post.title}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="router.push({ name: 'post-editor', params: { id: post.id } })">
                            <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit post
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="post.state === 'published'" as="a" :href="post.url" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                        </DropdownMenuItem>
                        <DropdownMenuItem v-else class="lm-item" @select="emit('preview')">
                            <span class="lm-icon" style="--tone: #a78bfa"><Eye class="size-4" /></span>Preview the saved version
                        </DropdownMenuItem>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete post
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="po-icon-btn po-open" :aria-label="`Open ${post.title}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Card (grid view, and every view when the list is narrow). */
.po-item {
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
    animation: po-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }
}

.po-media {
    grid-area: media;
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: var(--color-canvas);
}

.po-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1);
}

.po-item:hover .po-photo {
    transform: scale(1.05);
}

.po-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c2410c;
    background: linear-gradient(135deg, #ffedd5, #fff7ed);
}

.po-star {
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

.po-main {
    grid-area: main;
    align-self: start;
    min-width: 0;
    padding: 1rem 1.1rem 0;
}

.po-kicker {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.85rem;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--color-ink-soft);

    > span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
}

.po-category {
    color: #6d28d9;
}

.po-title {
    margin-top: 0.4rem;
    font-size: 16.5px;
    font-weight: 700;
    line-height: 1.35;
    color: var(--color-ink);
}

.po-excerpt {
    margin-top: 0.35rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 13.5px;
    line-height: 1.55;
    color: var(--color-ink-soft);
}

.po-tags {
    margin-top: 0.65rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.po-tag {
    display: inline-flex;
    max-width: 100%;
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

.po-cell {
    min-width: 0;
    padding: 0.85rem 1.1rem 1rem;
}

.po-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.po-status {
    grid-area: status;
}

.po-status .po-label,
.po-date {
    display: none;
}

.po-pill {
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

.po-date {
    grid-area: date;
    align-items: center;
    gap: 0.65rem;
}

.po-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.6rem 0.9rem 0.85rem 0;
    cursor: default;
}

.po-icon-btn {
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

.po-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .po-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* List view with room: one row; the date stays in the kicker line. */
@container po-list (min-width: 860px) {
    .po-item.is-list {
        grid-template-columns: 15rem minmax(0, 1fr) auto auto;
        grid-template-rows: auto;
        grid-template-areas: 'media main status actions';
        min-height: 150px;

        .po-media {
            align-self: stretch;
            aspect-ratio: auto;
        }

        .po-main {
            align-self: center;
            padding: 1rem 1.25rem;
        }

        .po-status {
            width: 10rem;
            align-self: stretch;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            border-left: 1px solid var(--color-line);
            padding: 0.75rem 1.25rem;
        }

        .po-status .po-label {
            display: block;
            margin-bottom: 0.4rem;
        }

        .po-actions {
            padding: 0 1rem 0 0.5rem;
        }
    }
}

/* Wide list: the publish date gets its own column. */
@container po-list (min-width: 1180px) {
    .po-item.is-list {
        grid-template-columns: 16rem minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'media main status date actions';

        .po-date {
            width: 12rem;
            display: flex;
            align-self: stretch;
            border-left: 1px solid var(--color-line);
            padding: 0.75rem 1.25rem;
        }

        .po-date-inline {
            display: none;
        }
    }
}

@keyframes po-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .po-item {
        animation: none;
    }

    .po-item,
    .po-photo,
    .po-open {
        transition: none;
    }

    .po-item:hover,
    .po-item:hover .po-photo {
        transform: none;
    }
}
</style>
