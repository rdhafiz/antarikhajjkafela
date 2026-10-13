<script setup lang="ts">
import { computed, ref } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { ChevronRight, EllipsisVertical, ExternalLink, Eye, EyeOff, GripVertical, Link2, Package as PackageIcon, Pencil, Plane, Trash2 } from 'lucide-vue-next';

export interface ServiceItem {
    id: number;
    name: string;
    slug: string;
    url: string;
    summary: string;
    is_active: boolean;
    packages_count: number;
    live_packages_count: number;
    image_url: string | null;
}

/**
 * One service: a wide row when the list has room (container queries), a card
 * otherwise. The grip handle (`.drag`) reorders services.
 */
const props = defineProps<{
    service: ServiceItem;
    /** SVG of the service's icon, from the shared icon set. */
    iconSvg: string;
    index: number;
    canSort: boolean;
    canUpdate: boolean;
    canDelete: boolean;
}>();
const emit = defineEmits<{ open: []; edit: []; toggle: []; remove: [] }>();

// Dragging moves the element, which would replay the entrance animation; run it once.
const entered = ref(false);

const packages = computed(() => `${props.service.packages_count} ${props.service.packages_count === 1 ? 'package' : 'packages'}`);
</script>

<template>
    <article class="sv-item group" :class="{ 'is-hidden': !service.is_active, 'is-entered': entered }" :style="{ '--i': index }" @click="emit('open')" @animationend.self="entered = true">
        <button v-if="canSort" type="button" class="drag sv-drag" :aria-label="`Drag ${service.name} to reorder`" @click.stop>
            <GripVertical class="size-4" />
        </button>

        <div class="sv-media">
            <img v-if="service.image_url" :src="service.image_url" alt="" class="sv-photo" loading="lazy" />
            <div v-else class="sv-photo sv-photo-empty" />
            <span class="sv-icon" aria-hidden="true">
                <span v-if="iconSvg" class="[&_svg]:size-5" v-html="iconSvg" />
                <Plane v-else class="size-5" />
            </span>
        </div>

        <div class="sv-main">
            <h2 class="sv-name">{{ service.name }}</h2>
            <p class="sv-slug"><Link2 class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">/{{ service.slug }}</span></p>
            <p v-if="service.summary" class="sv-summary">{{ service.summary }}</p>
        </div>

        <div class="sv-cell sv-count-cell">
            <div class="sv-count">
                <PackageIcon class="size-6 shrink-0" aria-hidden="true" />
                <span class="min-w-0">
                    <span class="block text-[18px] leading-tight font-bold whitespace-nowrap text-ink">{{ packages }}</span>
                    <span class="block text-[12.5px] font-semibold whitespace-nowrap">{{ service.live_packages_count }} live on the website</span>
                </span>
            </div>
        </div>

        <div class="sv-cell sv-visibility">
            <span class="sv-label">Website</span>
            <span class="sv-pill" :class="service.is_active ? 'is-on' : 'is-off'">
                <component :is="service.is_active ? Eye : EyeOff" class="size-4" aria-hidden="true" />{{ service.is_active ? 'Shown' : 'Hidden' }}
            </span>
        </div>

        <div class="sv-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="sv-icon-btn" :aria-label="`More actions for ${service.name}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem v-if="canUpdate" class="lm-item" @select="emit('edit')">
                            <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit service
                        </DropdownMenuItem>
                        <DropdownMenuItem as="a" :href="service.url" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="canUpdate" class="lm-item" @select="emit('toggle')">
                            <span class="lm-icon" style="--tone: #34d399"><component :is="service.is_active ? EyeOff : Eye" class="size-4" /></span>{{ service.is_active ? 'Hide from the website' : 'Show on the website' }}
                        </DropdownMenuItem>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete service
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="sv-icon-btn sv-open" :aria-label="`Open ${service.name}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Narrow (default): a card with the photo on top. */
.sv-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'media media'
        'main main'
        'count count'
        'visibility actions';
    align-items: center;
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: sv-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 45ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-entered {
        animation: none;
    }

    &.is-hidden .sv-photo {
        filter: grayscale(0.85);
        opacity: 0.75;
    }
}

.sv-drag {
    position: absolute;
    top: 0.65rem;
    left: 0.65rem;
    z-index: 2;
    display: flex;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    background: rgb(255 255 255 / 0.92);
    color: var(--color-ink-soft);
    box-shadow: 0 4px 12px rgb(15 23 42 / 0.16);
    cursor: grab;

    &:active {
        cursor: grabbing;
    }

    &:hover {
        color: var(--color-ink);
    }
}

.sv-media {
    grid-area: media;
    position: relative;
    aspect-ratio: 16 / 7;
    overflow: hidden;
    background: var(--color-canvas);
}

.sv-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1), filter 300ms ease;
}

.sv-item:hover .sv-photo {
    transform: scale(1.05);
}

.sv-photo-empty {
    background: linear-gradient(135deg, #ede9fe, #e0f2fe);
}

.sv-icon {
    position: absolute;
    bottom: 0.65rem;
    left: 0.65rem;
    display: flex;
    width: 2.6rem;
    height: 2.6rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    background: #fff;
    color: #6d28d9;
    box-shadow: 0 6px 16px rgb(15 23 42 / 0.2);
}

.sv-main {
    grid-area: main;
    min-width: 0;
    padding: 1rem 1.1rem 0;
}

.sv-name {
    font-size: 17px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--color-ink);
}

.sv-slug {
    margin-top: 0.15rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 13px;
    color: var(--color-ink-soft);
}

.sv-summary {
    margin-top: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 13.5px;
    line-height: 1.5;
    color: var(--color-ink-soft);
}

.sv-cell {
    min-width: 0;
    padding: 0.5rem 1.1rem;
}

.sv-count-cell {
    grid-area: count;
    padding-top: 0.85rem;
}

.sv-count {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    border-radius: 0.9rem;
    padding: 0.75rem 1rem;
    background: color-mix(in oklab, #a78bfa 13%, white);
    color: color-mix(in oklab, #a78bfa 45%, black);
}

.sv-label {
    display: none;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.sv-visibility {
    grid-area: visibility;
    padding-bottom: 1rem;
}

.sv-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;

    &.is-on {
        background: #e8f8ef;
        color: #157347;
    }

    &.is-off {
        background: #eef1f5;
        color: #475569;
    }
}

.sv-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0 0.9rem 1rem 0;
    cursor: default;
}

.sv-icon-btn {
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

.sv-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .sv-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* Wide: one row, the grip handle on the left. */
@container sv-list (min-width: 760px) {
    .sv-item {
        grid-template-columns: auto 13rem minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'drag media main count visibility actions';
        min-height: 136px;
    }

    .sv-drag {
        grid-area: drag;
        position: static;
        margin-left: 0.6rem;
        width: 2rem;
        height: 2.5rem;
        background: transparent;
        box-shadow: none;

        &:hover {
            background: var(--color-canvas);
        }
    }

    .sv-media {
        align-self: stretch;
        aspect-ratio: auto;
    }

    .sv-main {
        padding: 1rem 1.25rem;
    }

    .sv-count-cell {
        padding: 0.75rem 1rem 0.75rem 0;
    }

    .sv-visibility {
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .sv-label {
        display: block;
        margin-bottom: 0.4rem;
    }

    .sv-actions {
        padding: 0 1rem 0 0.25rem;
    }
}

@keyframes sv-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sv-item {
        animation: none;
    }

    .sv-item,
    .sv-photo,
    .sv-open {
        transition: none;
    }

    .sv-item:hover,
    .sv-item:hover .sv-photo {
        transform: none;
    }
}
</style>
