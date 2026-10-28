<script setup lang="ts">
import { computed } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { CalendarDays, ChevronRight, CircleCheck, EllipsisVertical, FileText, Globe, Loader, Mail, MailOpen, Megaphone, MessageCircle, MessageSquareText, Phone, Sparkles, Trash2, UserRound } from 'lucide-vue-next';
import { formatDate, formatDatesIn, formatTime, initials, titleCase } from '@admin/lib/format';
import { inquiryStatus, inquirySource, whatsappLink, type InquiryRow, type InquiryStatus } from './types';

/**
 * One inquiry: a wide row when the list has room (container queries), a card otherwise.
 */
const props = defineProps<{ row: InquiryRow; index: number; canUpdate: boolean; canDelete: boolean; selected?: boolean }>();
const emit = defineEmits<{ open: []; status: [status: InquiryStatus]; remove: []; select: [selected: boolean]; read: [read: boolean] }>();

const status = computed(() => inquiryStatus[props.row.status]);
const source = computed(() => inquirySource(props.row.source));
const name = computed(() => props.row.name || props.row.phone || 'Anonymous');
const whatsapp = computed(() => whatsappLink(props.row.phone));

/** The message, or a campaign form's other answers ("City: Dhaka · Travellers: 3"), as a short preview. */
const preview = computed(() => {
    if (props.row.data.message) return props.row.data.message;
    return Object.entries(props.row.data)
        .filter(([key, value]) => !['page', 'name', 'phone', 'email'].includes(key) && value)
        .map(([key, value]) => `${titleCase(key)}: ${formatDatesIn(value)}`)
        .join(' · ');
});

const statusIcons = { new: Sparkles, in_progress: Loader, closed: CircleCheck } as const;
const otherStatuses = computed(() => (Object.keys(inquiryStatus) as InquiryStatus[]).filter((key) => key !== props.row.status));
</script>

<template>
    <article class="iq-item group relative" :class="{ 'is-unread': !row.read, 'ring-2 ring-brand': selected }" :style="{ '--i': index, '--src': source.tone, '--st': status.tone }" @click="emit('open')">
        <label class="row-check" @click.stop>
            <input type="checkbox" :checked="selected" :aria-label="`Select inquiry from ${name}`" @change="emit('select', ($event.target as HTMLInputElement).checked)" />
        </label>
        <div class="iq-avatar" aria-hidden="true">
            <template v-if="row.name">{{ initials(row.name) }}</template>
            <UserRound v-else class="size-5" />
            <component :is="row.source === 'contact_form' ? MessageSquareText : Megaphone" class="iq-avatar-badge" />
        </div>

        <div class="iq-main">
            <h2 class="iq-name">
                <span v-if="!row.read" class="iq-dot" title="Unread"><span class="sr-only">Unread: </span></span>
                <span class="truncate">{{ name }}</span>
            </h2>
            <p class="iq-contact">
                <span v-if="row.phone" class="tabular-nums">{{ row.phone }}</span>
                <span v-if="row.phone && row.email" aria-hidden="true"> · </span>
                <span v-if="row.email" class="truncate">{{ row.email }}</span>
                <span class="iq-date-inline"> · {{ formatDate(row.created_at, true) }}</span>
            </p>
            <p v-if="preview" class="iq-preview" :class="{ 'is-quote': row.data.message }">{{ row.data.message ? `“${preview}”` : preview }}</p>
            <p class="iq-tags">
                <span class="iq-tag" :style="{ '--chip': source.tone }"><component :is="row.source === 'contact_form' ? MessageSquareText : Megaphone" class="size-3.5 shrink-0" aria-hidden="true" />{{ source.label }}</span>
                <span v-if="row.page" class="iq-tag" style="--chip: #60a5fa" :title="row.page.title"><FileText class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ row.page.title }}</span></span>
                <span v-if="row.locale" class="iq-tag" style="--chip: #2dd4bf"><Globe class="size-3.5 shrink-0" aria-hidden="true" />{{ row.locale.toUpperCase() }}</span>
            </p>
        </div>

        <div class="iq-cell iq-status">
            <span class="iq-label">Status</span>
            <span class="iq-pill"><component :is="statusIcons[row.status]" class="size-4" aria-hidden="true" />{{ status.label }}</span>
        </div>

        <div class="iq-cell iq-date">
            <CalendarDays class="size-5 shrink-0 text-ink-soft" aria-hidden="true" />
            <span>
                <span class="iq-label">Received</span>
                <span class="block text-[14px] font-medium whitespace-nowrap text-ink">{{ formatDate(row.created_at) }}</span>
                <span class="block text-[12.5px] whitespace-nowrap text-ink-muted">{{ formatTime(row.created_at) }}</span>
            </span>
        </div>

        <div class="iq-actions" @click.stop>
            <DropdownMenuRoot :modal="false">
                <DropdownMenuTrigger class="iq-icon-btn" :aria-label="`More actions for ${name}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem class="lm-item" @select="emit('read', !row.read)">
                            <span class="lm-icon" style="--tone: #fbbf24"><MailOpen class="size-4" /></span>{{ row.read ? 'Mark as unread' : 'Mark as read' }}
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="row.phone" as="a" :href="`tel:${row.phone}`" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><Phone class="size-4" /></span>Call {{ row.phone }}
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="whatsapp" as="a" :href="whatsapp" target="_blank" rel="noopener" class="lm-item">
                            <span class="lm-icon" style="--tone: #34d399"><MessageCircle class="size-4" /></span>WhatsApp
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="row.email" as="a" :href="`mailto:${row.email}`" class="lm-item">
                            <span class="lm-icon" style="--tone: #f472b6"><Mail class="size-4" /></span>Email
                        </DropdownMenuItem>
                        <template v-if="canUpdate">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem v-for="key in otherStatuses" :key="key" class="lm-item" @select="emit('status', key)">
                                <span class="lm-icon" :style="{ '--tone': inquiryStatus[key].tone }"><component :is="statusIcons[key]" class="size-4" /></span>Mark as {{ inquiryStatus[key].label.toLowerCase() }}
                            </DropdownMenuItem>
                        </template>
                        <template v-if="canDelete">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete inquiry
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="iq-icon-btn iq-open" :aria-label="`Open inquiry from ${name}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Narrow (default): a card. */
.iq-item {
    position: relative;
    display: grid;
    grid-template-columns: 4.25rem minmax(0, 1fr) auto;
    grid-template-areas:
        'avatar main main'
        'status status actions';
    align-items: start;
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: iq-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-unread {
        border-color: color-mix(in oklab, #fbbf24 45%, white);
        background: linear-gradient(90deg, color-mix(in oklab, #fbbf24 7%, white), #fff 40%);
    }
}

.iq-avatar {
    grid-area: avatar;
    position: relative;
    margin: 1rem 0 0 1rem;
    display: flex;
    width: 2.9rem;
    height: 2.9rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: color-mix(in oklab, var(--src) 18%, white);
    color: color-mix(in oklab, var(--src) 45%, black);
    font-size: 15px;
    font-weight: 700;
}

.iq-avatar-badge {
    position: absolute;
    right: -0.2rem;
    bottom: -0.2rem;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    border: 2px solid #fff;
    background: color-mix(in oklab, var(--src) 70%, black 10%);
    color: #fff;
    padding: 0.18rem;
}

.iq-main {
    grid-area: main;
    min-width: 0;
    padding: 1rem 1.1rem 0 0.75rem;
}

.iq-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--color-ink);
}

.iq-dot {
    width: 0.55rem;
    height: 0.55rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: #eab308;
    box-shadow: 0 0 0 3px rgb(234 179 8 / 0.22);
}

.iq-contact {
    margin-top: 0.15rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0 0.15rem;
    font-size: 13px;
    color: var(--color-ink-soft);
}

.iq-preview {
    margin-top: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 13.5px;
    line-height: 1.5;
    color: var(--color-ink);
}

.iq-tags {
    margin-top: 0.6rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.iq-tag {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.25rem 0.65rem;
    font-size: 12.5px;
    font-weight: 600;
    white-space: nowrap;
    background: color-mix(in oklab, var(--chip) 13%, white);
    color: color-mix(in oklab, var(--chip) 45%, black);
}

.iq-cell {
    min-width: 0;
    padding: 0.75rem 1.1rem 1rem;
}

.iq-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.iq-status {
    grid-area: status;
    align-self: center;
}

.iq-status .iq-label,
.iq-date {
    display: none;
}

.iq-pill {
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

.iq-date {
    grid-area: date;
    align-items: center;
    gap: 0.65rem;
}

.iq-actions {
    grid-area: actions;
    align-self: center;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.5rem 0.9rem 0.75rem 0;
    cursor: default;
}

.iq-icon-btn {
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

.iq-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .iq-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* Medium: one row; the date stays in the contact line. */
@container iq-list (min-width: 760px) {
    .iq-item {
        grid-template-columns: 5rem minmax(0, 1fr) auto auto;
        grid-template-areas: 'avatar main status actions';
        align-items: center;
    }

    .iq-avatar {
        margin: 0 0 0 1.25rem;
        align-self: center;
    }

    .iq-main {
        padding: 1rem 1.25rem 1rem 0.5rem;
    }

    .iq-status {
        width: 10.5rem;
        align-self: stretch;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.1rem;
    }

    .iq-status .iq-label {
        display: block;
        margin-bottom: 0.4rem;
    }

    .iq-actions {
        padding: 0 1rem 0 0.25rem;
    }
}

/* Wide: the received date gets its own column. */
@container iq-list (min-width: 1100px) {
    .iq-item {
        grid-template-columns: 5rem minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'avatar main status date actions';
    }

    .iq-date {
        width: 11.5rem;
        display: flex;
        align-self: stretch;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .iq-date-inline {
        display: none;
    }
}

@keyframes iq-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .iq-item {
        animation: none;
    }

    .iq-item,
    .iq-open {
        transition: none;
    }

    .iq-item:hover {
        transform: none;
    }
}
</style>
