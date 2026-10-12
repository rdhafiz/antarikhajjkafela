<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { CalendarCheck, ChevronDown, Globe, Inbox, LogOut, Menu, PanelLeftClose, PanelLeftOpen, UserRound } from 'lucide-vue-next';
import { navigation } from '@admin/navigation';
import { useAuthStore } from '@admin/stores/auth';
import { useAppStore } from '@admin/stores/app';
import { initials } from '@admin/lib/format';

defineProps<{ collapsed: boolean }>();
const emit = defineEmits<{ 'toggle-collapse': []; 'open-menu': [] }>();

const auth = useAuthStore();
const app = useAppStore();
const route = useRoute();
const router = useRouter();

/** Where the admin is: the sidebar section and module of the current page, with its colour. */
const place = computed(() => {
    for (const section of navigation) {
        for (const item of section.items) {
            const match = item.to === '/admin' ? route.path === '/admin' : route.path.startsWith(item.to);
            if (match) return { section: section.label ?? 'Overview', item };
        }
    }

    return null;
});
const title = computed(() => String(route.meta.title ?? place.value?.item.label ?? 'Dashboard'));

async function signOut(): Promise<void> {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <header class="tb sticky top-0 z-30 flex h-16 items-center gap-2 px-3 text-header-text sm:gap-3 sm:px-6 lg:h-[72px]">
        <button type="button" class="tb-icon lg:hidden" aria-label="Open menu" @click="emit('open-menu')">
            <Menu class="size-5" />
        </button>
        <button type="button" class="tb-icon hidden lg:flex" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" @click="emit('toggle-collapse')">
            <PanelLeftOpen v-if="collapsed" class="size-5" />
            <PanelLeftClose v-else class="size-5" />
        </button>

        <!-- Where you are -->
        <div class="tb-in flex min-w-0 flex-1 items-center gap-3" style="--i: 1">
            <span v-if="place" class="tb-place hidden size-9 shrink-0 items-center justify-center rounded-xl sm:flex" :style="{ '--tone': place.item.tone }" aria-hidden="true">
                <component :is="place.item.icon" class="size-[18px]" />
            </span>
            <div class="min-w-0 leading-tight">
                <p v-if="place" class="hidden text-[11px] font-semibold tracking-[0.14em] text-ink-soft uppercase sm:block">{{ place.section }}</p>
                <p class="truncate text-[15px] font-bold text-ink sm:text-[16px]">{{ title }}</p>
            </div>
        </div>

        <!-- Quick links -->
        <div class="tb-in flex items-center gap-1.5 sm:gap-2" style="--i: 2">
            <RouterLink v-if="auth.can('bookings.view')" to="/admin/bookings" class="tb-icon relative hidden sm:flex" :title="`Bookings${app.bookingsUnread ? ` (${app.bookingsUnread} new)` : ''}`">
                <CalendarCheck class="size-5" />
                <span class="sr-only">Bookings</span>
                <span v-if="app.bookingsUnread > 0" class="tb-dot" aria-hidden="true">{{ app.bookingsUnread > 9 ? '9+' : app.bookingsUnread }}</span>
            </RouterLink>
            <RouterLink v-if="auth.can('inquiries.view')" to="/admin/inquiries" class="tb-icon relative hidden sm:flex" :title="`Inquiries${app.inquiriesUnread ? ` (${app.inquiriesUnread} new)` : ''}`">
                <Inbox class="size-5" />
                <span class="sr-only">Inquiries</span>
                <span v-if="app.inquiriesUnread > 0" class="tb-dot" aria-hidden="true">{{ app.inquiriesUnread > 9 ? '9+' : app.inquiriesUnread }}</span>
            </RouterLink>

            <a :href="app.siteUrl" target="_blank" rel="noopener" class="tb-visit group">
                <Globe class="size-[18px] transition-[rotate] duration-700 group-hover:rotate-[20deg]" aria-hidden="true" />
                <span class="hidden md:inline">Visit website</span>
                <span class="sr-only md:hidden">Visit website</span>
                <span class="sr-only">(opens in a new tab)</span>
            </a>

            <DropdownMenuRoot v-if="auth.user">
                <DropdownMenuTrigger class="tb-user flex items-center gap-2.5 rounded-xl py-1 pr-1.5 pl-1 sm:pr-2.5">
                    <span class="flex size-9 items-center justify-center rounded-full bg-brand text-[13px] font-bold text-white ring-2 ring-gold/60 ring-offset-2 ring-offset-white">
                        {{ initials(auth.user.name) }}
                    </span>
                    <span class="hidden text-left leading-tight xl:block">
                        <span class="block max-w-40 truncate text-[13px] font-semibold text-ink">{{ auth.user.name }}</span>
                        <span class="block text-[12px] text-ink-soft">{{ auth.user.is_super_admin ? 'Super admin' : auth.user.roles[0] ?? 'Admin' }}</span>
                    </span>
                    <ChevronDown class="hidden size-4 text-ink-muted sm:block" />
                    <span class="sr-only">Account menu</span>
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" :side-offset="10" class="tb-menu z-50 min-w-60 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                        <DropdownMenuLabel class="flex items-center gap-3 px-2.5 py-2.5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-bold text-white">{{ initials(auth.user.name) }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ auth.user.name }}</span>
                                <span class="block truncate text-[12px] text-ink-muted">{{ auth.user.email }}</span>
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1 h-px bg-line" />
                        <DropdownMenuItem class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-ink outline-none data-[highlighted]:bg-canvas" @select="router.push({ name: 'profile' })">
                            <UserRound class="size-4 text-ink-muted" /> My profile
                        </DropdownMenuItem>
                        <DropdownMenuItem as="a" :href="app.siteUrl" target="_blank" rel="noopener" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-ink outline-none data-[highlighted]:bg-canvas">
                            <Globe class="size-4 text-ink-muted" /> Visit website
                        </DropdownMenuItem>
                        <DropdownMenuSeparator class="my-1 h-px bg-line" />
                        <DropdownMenuItem class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-red-600 outline-none data-[highlighted]:bg-red-50" @select="signOut">
                            <LogOut class="size-4" /> Sign out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>
    </header>
</template>
