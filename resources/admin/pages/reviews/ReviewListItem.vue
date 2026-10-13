<script setup lang="ts">
import { computed, ref } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { ChevronRight, CircleCheck, EllipsisVertical, Eye, EyeOff, GripVertical, PenLine, Pencil, Play, Star, StarOff, Trash2 } from 'lucide-vue-next';
import { initials } from '@admin/lib/format';

export interface ReviewItem {
    id: number;
    name: string;
    intro: string;
    text: string;
    photo_url: string | null;
    video_id: string | null;
    has_video: boolean;
    rating: number | null;
    is_featured: boolean;
    is_published: boolean;
}

/**
 * One review: a wide row when the list has room (container queries), a card otherwise.
 * The grip handle (`.drag`) reorders reviews.
 */
const props = defineProps<{ review: ReviewItem; index: number; canSort: boolean; canUpdate: boolean; canDelete: boolean }>();
const emit = defineEmits<{ open: []; edit: []; toggle: []; feature: []; remove: [] }>();

// Dragging moves the element, which would replay the entrance animation; run it once.
const entered = ref(false);

const status = computed(() => (props.review.is_published ? { label: 'Shown', icon: CircleCheck, cls: 'is-green' } : { label: 'Hidden', icon: EyeOff, cls: 'is-slate' }));
</script>

<template>
    <article
        class="rv-item group"
        :class="{ 'is-hidden': !review.is_published, 'is-featured': review.is_featured, 'is-entered': entered }"
        :style="{ '--i': index }"
        @click="emit('open')"
        @animationend.self="entered = true"
    >
        <button v-if="canSort" type="button" class="drag rv-drag" :aria-label="`Drag the review by ${review.name} to reorder`" @click.stop>
            <GripVertical class="size-4" />
        </button>

        <div class="rv-person">
            <span class="rv-avatar" aria-hidden="true">
                <img v-if="review.photo_url" :src="review.photo_url" alt="" class="size-full rounded-full object-cover" loading="lazy" />
                <template v-else>{{ initials(review.name) }}</template>
            </span>
            <span class="min-w-0">
                <span class="rv-name">
                    <span class="rv-name-text">{{ review.name }}</span>
                    <span v-if="review.is_featured" class="rv-featured" title="Featured"><Star class="size-3 fill-current" aria-hidden="true" />Featured</span>
                </span>
                <span v-if="review.intro" class="rv-intro">{{ review.intro }}</span>
                <span v-if="review.rating" class="rv-stars" role="img" :aria-label="`${review.rating} out of 5 stars`">
                    <Star v-for="i in 5" :key="i" class="size-3.5" :class="i <= review.rating ? 'fill-current' : 'is-empty'" aria-hidden="true" />
                </span>
            </span>
        </div>

        <div class="rv-body">
            <p v-if="review.text" class="rv-quote">{{ review.text }}</p>
            <p v-else class="rv-quote is-muted">Video review</p>
        </div>

        <div class="rv-video">
            <span v-if="review.video_id" class="rv-thumb">
                <img :src="`https://i.ytimg.com/vi/${review.video_id}/mqdefault.jpg`" alt="" class="size-full object-cover" loading="lazy" />
                <span class="rv-play" aria-hidden="true"><Play class="size-4 fill-current" /></span>
                <span class="sr-only">Has a video</span>
            </span>
            <span v-else-if="review.has_video" class="rv-type"><Play class="size-3.5" aria-hidden="true" />Video</span>
            <span v-else class="rv-type is-text"><PenLine class="size-3.5" aria-hidden="true" />Written</span>
        </div>

        <div class="rv-status">
            <span class="rv-label">Website</span>
            <span class="rv-pill" :class="status.cls"><component :is="status.icon" class="size-4" aria-hidden="true" />{{ status.label }}</span>
        </div>

        <div class="rv-actions" @click.stop>
            <DropdownMenuRoot v-if="canUpdate || canDelete" :modal="false">
                <DropdownMenuTrigger class="rv-icon-btn" :aria-label="`More actions for the review by ${review.name}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <template v-if="canUpdate">
                            <DropdownMenuItem class="lm-item" @select="emit('edit')">
                                <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit review
                            </DropdownMenuItem>
                            <DropdownMenuItem class="lm-item" @select="emit('feature')">
                                <span class="lm-icon" style="--tone: #f472b6"><component :is="review.is_featured ? StarOff : Star" class="size-4" /></span>{{ review.is_featured ? 'Remove from featured' : 'Mark as featured' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem class="lm-item" @select="emit('toggle')">
                                <span class="lm-icon" style="--tone: #34d399"><component :is="review.is_published ? EyeOff : Eye" class="size-4" /></span>{{ review.is_published ? 'Hide from the website' : 'Show on the website' }}
                            </DropdownMenuItem>
                        </template>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator v-if="canUpdate" class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete review
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="rv-icon-btn rv-open" :aria-label="`Open the review by ${review.name}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Narrow (default): a card. */
.rv-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'person video'
        'body body'
        'status actions';
    align-items: center;
    gap: 0 0.75rem;
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: rv-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-entered {
        animation: none;
    }

    &.is-featured {
        border-color: color-mix(in oklab, #fbbf24 50%, white);
        background: linear-gradient(90deg, color-mix(in oklab, #fbbf24 7%, white), #fff 45%);
    }

    /* Hidden: dashed edge and a grey avatar; the text stays readable. */
    &.is-hidden {
        border-style: dashed;
        border-color: #cbd5e1;
        background: #fbfcfd;
    }

    &.is-hidden .rv-avatar {
        filter: grayscale(1);
    }
}

.rv-drag {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    z-index: 2;
    display: flex;
    width: 2rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    color: var(--color-ink-soft);
    cursor: grab;

    &:hover {
        background: var(--color-canvas);
        color: var(--color-ink);
    }

    &:active {
        cursor: grabbing;
    }
}

.rv-person {
    grid-area: person;
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.8rem;
    padding: 1rem 0 0 1.1rem;
}

.rv-avatar {
    display: flex;
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: color-mix(in oklab, #f472b6 18%, white);
    color: color-mix(in oklab, #f472b6 40%, black);
    font-size: 15px;
    font-weight: 700;
    box-shadow: 0 0 0 3px #fff, 0 0 0 4px color-mix(in oklab, #f472b6 30%, white);
}

.rv-name {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.2rem 0.5rem;
    font-size: 15.5px;
    font-weight: 700;
    color: var(--color-ink);
}

.rv-featured {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    background: #fef3c7;
    padding: 0.1rem 0.5rem;
    font-size: 11.5px;
    font-weight: 700;
    color: #92400e;
}

.rv-intro {
    display: block;
    margin-top: 0.1rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12.5px;
    color: var(--color-ink-soft);
}

.rv-stars {
    margin-top: 0.25rem;
    display: flex;
    gap: 0.1rem;
    color: #eab308;

    .is-empty {
        color: #e2e8f0;
    }
}

.rv-body {
    grid-area: body;
    min-width: 0;
    padding: 0.75rem 1.1rem 0;
}

.rv-quote {
    position: relative;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    border-left: 3px solid color-mix(in oklab, #f472b6 40%, white);
    padding-left: 0.75rem;
    font-size: 13.5px;
    line-height: 1.6;
    color: var(--color-ink);

    &.is-muted {
        color: var(--color-ink-soft);
        font-style: italic;
    }
}

.rv-video {
    grid-area: video;
    align-self: start;
    padding: 1rem 2.9rem 0 0;
}

.rv-thumb {
    position: relative;
    display: block;
    width: 4.5rem;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: 0.6rem;
    background: #0b1220;
}

.rv-play {
    position: absolute;
    inset: 0;
    margin: auto;
    display: flex;
    width: 1.9rem;
    height: 1.9rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgb(220 38 38 / 0.92);
    color: #fff;
    padding-left: 0.1rem;
}

.rv-name-text {
    min-width: 0;
    overflow-wrap: anywhere;
}

/* On cards a missing video thumbnail already says "written"; the chip shows on wide rows. */
.rv-type.is-text {
    display: none;
}

.rv-type {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    background: #fee2e2;
    padding: 0.25rem 0.6rem;
    font-size: 12px;
    font-weight: 600;
    color: #b91c1c;

    &.is-text {
        background: #e0f2fe;
        color: #075985;
    }
}

.rv-label {
    display: none;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.rv-status {
    grid-area: status;
    padding: 0.85rem 0 1rem 1.1rem;
}

.rv-pill {
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
    &.is-slate {
        background: #eef1f5;
        color: #475569;
    }
}

.rv-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.6rem 0.9rem 0.85rem 0;
    cursor: default;
}

.rv-icon-btn {
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

.rv-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .rv-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* Wide: one row with the grip on the left. */
@container rv-list (min-width: 820px) {
    .rv-item {
        grid-template-columns: auto minmax(14rem, 18rem) minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'drag person body video status actions';
        min-height: 112px;
        gap: 0;
    }

    .rv-drag {
        grid-area: drag;
        position: static;
        margin-left: 0.6rem;
    }

    .rv-person {
        padding: 1rem 1.25rem;
    }

    .rv-item:not(:has(.rv-drag)) .rv-person {
        padding-left: 1.4rem;
    }

    .rv-body {
        align-self: stretch;
        display: flex;
        align-items: center;
        border-left: 1px solid var(--color-line);
        padding: 0.9rem 1.25rem;
    }

    .rv-video {
        align-self: center;
        padding: 0 1rem 0 0;
    }

    .rv-thumb {
        width: 5.5rem;
    }

    .rv-type.is-text {
        display: inline-flex;
    }

    .rv-name-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rv-status {
        width: 9rem;
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .rv-label {
        display: block;
        margin-bottom: 0.4rem;
    }

    .rv-actions {
        padding: 0 1rem 0 0.5rem;
    }
}

@keyframes rv-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .rv-item {
        animation: none;
    }

    .rv-item,
    .rv-open {
        transition: none;
    }

    .rv-item:hover {
        transform: none;
    }
}
</style>
