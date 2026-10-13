<script setup lang="ts">
import { computed } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { ChevronRight, CircleCheck, Clock, Crown, EllipsisVertical, Mail, Pencil, Phone, ShieldCheck, Trash2, UserCheck, UserX } from 'lucide-vue-next';
import { formatDate, initials } from '@admin/lib/format';
import type { AdminUser } from '@admin/types';

/**
 * One admin user: a wide row when the list has room (container queries), a card otherwise.
 */
const props = defineProps<{
    user: AdminUser;
    roleLabel: (name: string) => string;
    isSelf: boolean;
    index: number;
    canUpdate: boolean;
    canDelete: boolean;
}>();
const emit = defineEmits<{ open: []; edit: []; toggle: []; remove: [] }>();

const superAdmin = computed(() => props.user.roles?.includes('super-admin') ?? false);

/** "Today", "3 days ago", … for the last sign-in. */
const lastSeen = computed(() => {
    if (!props.user.last_login_at) return 'Never signed in';
    const days = Math.floor((Date.now() - new Date(props.user.last_login_at).getTime()) / 86_400_000);
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 30) return `${days} days ago`;
    return formatDate(props.user.last_login_at);
});
const recent = computed(() => props.user.last_login_at && Date.now() - new Date(props.user.last_login_at).getTime() < 7 * 86_400_000);
const hasActions = computed(() => props.canUpdate || (props.canDelete && !props.isSelf));
</script>

<template>
    <article class="us-item group" :class="{ 'is-inactive': !user.is_active }" :style="{ '--i': index }" @click="emit('open')">
        <div class="us-person">
            <span class="us-avatar" :class="{ 'is-super': superAdmin }" aria-hidden="true">
                {{ initials(user.name) }}
                <span v-if="recent && user.is_active" class="us-online" title="Signed in this week" />
            </span>
            <span class="min-w-0">
                <span class="us-name">
                    <span class="us-name-text">{{ user.name }}</span>
                    <span v-if="isSelf" class="us-you">You</span>
                </span>
                <span class="us-contact"><Mail class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ user.email }}</span></span>
                <span v-if="user.phone" class="us-contact"><Phone class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ user.phone }}</span></span>
            </span>
        </div>

        <div class="us-roles">
            <span v-for="role in user.roles" :key="role" class="us-role" :class="{ 'is-super': role === 'super-admin' }">
                <component :is="role === 'super-admin' ? Crown : ShieldCheck" class="size-3.5" aria-hidden="true" />{{ roleLabel(role) }}
            </span>
            <span v-if="!user.roles?.length" class="us-role is-none">No role</span>
        </div>

        <div class="us-cell us-status">
            <span class="us-label">Status</span>
            <span class="us-pill" :class="user.is_active ? 'is-green' : 'is-slate'">
                <component :is="user.is_active ? CircleCheck : UserX" class="size-4" aria-hidden="true" />{{ user.is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <div class="us-cell us-seen">
            <Clock class="size-5 shrink-0 text-ink-soft" aria-hidden="true" />
            <span>
                <span class="us-label">Last sign-in</span>
                <span class="block text-[14px] font-medium whitespace-nowrap text-ink">{{ lastSeen }}</span>
            </span>
        </div>

        <div class="us-actions" @click.stop>
            <DropdownMenuRoot v-if="hasActions" :modal="false">
                <DropdownMenuTrigger class="us-icon-btn" :aria-label="`More actions for ${user.name}`">
                    <EllipsisVertical class="size-5" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuItem v-if="canUpdate" class="lm-item" @select="emit('edit')">
                            <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit user
                        </DropdownMenuItem>
                        <DropdownMenuItem as="a" :href="`mailto:${user.email}`" class="lm-item">
                            <span class="lm-icon" style="--tone: #38bdf8"><Mail class="size-4" /></span>Send an email
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="canUpdate && !isSelf" class="lm-item" @select="emit('toggle')">
                            <span class="lm-icon" :style="{ '--tone': user.is_active ? '#fb923c' : '#34d399' }"><component :is="user.is_active ? UserX : UserCheck" class="size-4" /></span>{{ user.is_active ? 'Deactivate account' : 'Activate account' }}
                        </DropdownMenuItem>
                        <template v-if="canDelete && !isSelf">
                            <DropdownMenuSeparator class="my-1 h-px bg-line" />
                            <DropdownMenuItem class="lm-item is-danger" @select="emit('remove')">
                                <span class="lm-icon"><Trash2 class="size-4" /></span>Delete user
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <button type="button" class="us-icon-btn us-open" :aria-label="`Open ${user.name}`" @click="emit('open')">
                <ChevronRight class="size-5" />
            </button>
        </div>
    </article>
</template>

<style scoped>
/* Narrow (default): a card. */
.us-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'person person'
        'roles roles'
        'status actions';
    align-items: center;
    overflow: hidden;
    border-radius: 1.15rem;
    border: 1px solid var(--color-line);
    background: #fff;
    box-shadow: var(--shadow-card);
    cursor: pointer;
    animation: us-in 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 40ms);
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #d5dde8;
        box-shadow: 0 18px 38px -22px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-inactive {
        border-style: dashed;
        border-color: #cbd5e1;
        background: #fbfcfd;
    }

    &.is-inactive .us-avatar {
        filter: grayscale(1);
    }
}

.us-person {
    grid-area: person;
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.1rem 0;
}

.us-avatar {
    position: relative;
    display: flex;
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: linear-gradient(135deg, color-mix(in oklab, #93c5fd 45%, white), color-mix(in oklab, #93c5fd 20%, white));
    color: color-mix(in oklab, #3b82f6 55%, black);
    font-size: 15px;
    font-weight: 700;

    &.is-super {
        background: linear-gradient(135deg, #fde68a, #fef3c7);
        color: #92400e;
        box-shadow: 0 0 0 2px #fff, 0 0 0 3.5px #fbbf24;
    }
}

.us-online {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 0.8rem;
    height: 0.8rem;
    border-radius: 999px;
    border: 2px solid #fff;
    background: #22c55e;
}

.us-name {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.2rem 0.5rem;
    font-size: 15.5px;
    font-weight: 700;
    color: var(--color-ink);
}

.us-name-text {
    min-width: 0;
    overflow-wrap: anywhere;
}

.us-you {
    border-radius: 999px;
    background: #e0f2fe;
    padding: 0.05rem 0.5rem;
    font-size: 11.5px;
    font-weight: 700;
    color: #075985;
}

.us-contact {
    margin-top: 0.15rem;
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.35rem;
    font-size: 12.5px;
    color: var(--color-ink-soft);
}

.us-roles {
    grid-area: roles;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.75rem 1.1rem 0;
}

.us-role {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    background: color-mix(in oklab, #4ade80 16%, white);
    padding: 0.25rem 0.65rem;
    font-size: 12.5px;
    font-weight: 600;
    color: color-mix(in oklab, #4ade80 30%, black);

    &.is-super {
        background: #fef3c7;
        color: #92400e;
    }

    &.is-none {
        background: var(--color-canvas);
        color: var(--color-ink-soft);
    }
}

.us-cell {
    min-width: 0;
    padding: 0.85rem 1.1rem 1rem;
}

.us-label {
    display: none;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
}

.us-status {
    grid-area: status;
}

.us-pill {
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

.us-seen {
    display: none;
    grid-area: seen;
    align-items: center;
    gap: 0.65rem;
}

.us-actions {
    grid-area: actions;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.6rem 0.9rem 0.85rem 0;
    cursor: default;
}

.us-icon-btn {
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

.us-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .us-item:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

/* Wide: one row. */
@container us-list (min-width: 820px) {
    .us-item {
        grid-template-columns: minmax(16rem, 1.3fr) minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'person roles status seen actions';
        min-height: 96px;
    }

    .us-person {
        padding: 0.9rem 1.25rem;
    }

    .us-roles {
        padding: 0.75rem 1.25rem;
    }

    .us-status,
    .us-seen {
        align-self: stretch;
        display: flex;
        border-left: 1px solid var(--color-line);
        padding: 0.75rem 1.25rem;
    }

    .us-status {
        width: 9rem;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
    }

    .us-seen {
        width: 12rem;
    }

    .us-label {
        display: block;
    }

    .us-status .us-label {
        margin-bottom: 0.4rem;
    }

    .us-actions {
        padding: 0 1rem 0 0.5rem;
    }
}

@keyframes us-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .us-item {
        animation: none;
    }

    .us-item,
    .us-open {
        transition: none;
    }

    .us-item:hover {
        transform: none;
    }
}
</style>
