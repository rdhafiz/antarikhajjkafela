<script setup lang="ts">
import { useRouter } from 'vue-router';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { ChevronDown, LogOut, Menu, PanelLeftClose, PanelLeftOpen, UserRound } from 'lucide-vue-next';
import { useAuthStore } from '@admin/stores/auth';
import { initials } from '@admin/lib/format';

defineProps<{ collapsed: boolean }>();
const emit = defineEmits<{ 'toggle-collapse': []; 'open-menu': [] }>();

const auth = useAuthStore();
const router = useRouter();

async function signOut(): Promise<void> {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-header px-4 text-header-text sm:px-6">
        <button
            type="button"
            class="-ml-1 rounded-lg p-2 text-ink-soft hover:bg-canvas hover:text-ink lg:hidden"
            aria-label="Open menu"
            @click="emit('open-menu')"
        >
            <Menu class="size-5" />
        </button>
        <button
            type="button"
            class="-ml-2 hidden rounded-lg p-2 text-ink-soft hover:bg-canvas hover:text-ink lg:block"
            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            @click="emit('toggle-collapse')"
        >
            <PanelLeftOpen v-if="collapsed" class="size-5" />
            <PanelLeftClose v-else class="size-5" />
        </button>

        <div class="flex-1" />

        <DropdownMenuRoot v-if="auth.user">
            <DropdownMenuTrigger
                class="flex items-center gap-2.5 rounded-xl py-1 pr-2 pl-1 hover:bg-canvas data-[state=open]:bg-canvas"
            >
                <span class="flex size-9 items-center justify-center rounded-full bg-brand text-[13px] font-bold text-white">
                    {{ initials(auth.user.name) }}
                </span>
                <span class="hidden text-left leading-tight sm:block">
                    <span class="block text-[13px] font-semibold">{{ auth.user.name }}</span>
                    <span class="block text-[12px] text-ink-muted">{{ auth.user.is_super_admin ? 'Super admin' : auth.user.roles[0] ?? 'Admin' }}</span>
                </span>
                <ChevronDown class="size-4 text-ink-muted" />
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    class="z-50 min-w-56 rounded-xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]"
                >
                    <DropdownMenuLabel class="px-2.5 py-2">
                        <p class="text-[13px] font-semibold text-ink">{{ auth.user.name }}</p>
                        <p class="truncate text-[12px] text-ink-muted">{{ auth.user.email }}</p>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator class="my-1 h-px bg-line" />
                    <DropdownMenuItem
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-ink outline-none data-[highlighted]:bg-canvas"
                        @select="router.push({ name: 'profile' })"
                    >
                        <UserRound class="size-4 text-ink-muted" /> My profile
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-red-600 outline-none data-[highlighted]:bg-red-50"
                        @select="signOut"
                    >
                        <LogOut class="size-4" /> Sign out
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenuPortal>
        </DropdownMenuRoot>
    </header>
</template>
