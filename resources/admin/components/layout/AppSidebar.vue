<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ExternalLink } from 'lucide-vue-next';
import brandIcon from '@admin/assets/brand-icon.webp';
import { navigation, type NavItem } from '@admin/navigation';
import { useAuthStore } from '@admin/stores/auth';
import { useAppStore } from '@admin/stores/app';

defineProps<{ collapsed?: boolean }>();
const emit = defineEmits<{ navigate: [] }>();

const auth = useAuthStore();
const app = useAppStore();
const route = useRoute();

const sections = computed(() =>
    navigation
        .map((section) => ({ ...section, items: section.items.filter((item) => auth.can(item.permission)) }))
        .filter((section) => section.items.length > 0),
);

function isActive(item: NavItem): boolean {
    return item.to === '/admin' ? route.path === '/admin' : route.path.startsWith(item.to);
}
</script>

<template>
    <nav class="flex h-full flex-col bg-sidebar text-sidebar-text" aria-label="Main">
        <RouterLink
            to="/admin"
            class="flex h-16 shrink-0 items-center gap-3 bg-sidebar-logo px-5"
            :class="{ 'justify-center px-0': collapsed }"
            @click="emit('navigate')"
        >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white p-1">
                <img :src="app.icon ?? brandIcon" alt="" class="size-full object-contain" />
            </span>
            <span v-if="!collapsed" class="min-w-0 leading-tight">
                <span class="block truncate text-[14px] font-bold text-white">{{ app.name }}</span>
                <span class="block text-[11px] font-medium tracking-wider text-sidebar-text/70 uppercase">Admin</span>
            </span>
        </RouterLink>

        <div class="scroll-thin flex-1 overflow-y-auto px-3 py-4">
            <div v-for="(section, i) in sections" :key="i" class="mb-5 last:mb-0">
                <p
                    v-if="section.label && !collapsed"
                    class="mb-1.5 px-3 text-[11px] font-bold tracking-[0.08em] text-sidebar-text/55 uppercase"
                >
                    {{ section.label }}
                </p>
                <div v-else-if="section.label" class="mx-3 mb-2 h-px bg-white/10" />

                <ul class="space-y-0.5">
                    <li v-for="item in section.items" :key="item.to">
                        <RouterLink
                            :to="item.to"
                            class="group relative flex h-10 items-center gap-3 rounded-lg px-3 text-[14px] font-medium transition-colors"
                            :class="[
                                isActive(item) ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white',
                                collapsed && 'justify-center px-0',
                            ]"
                            :title="collapsed ? item.label : undefined"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            @click="emit('navigate')"
                        >
                            <span
                                v-if="isActive(item)"
                                class="absolute top-2 bottom-2 left-0 w-[3px] rounded-r-full bg-sidebar-accent"
                                aria-hidden="true"
                            />
                            <component
                                :is="item.icon"
                                class="size-[18px] shrink-0"
                                :class="isActive(item) ? 'text-sidebar-accent' : 'opacity-80 group-hover:opacity-100'"
                            />
                            <span v-if="!collapsed" class="flex-1 truncate">{{ item.label }}</span>
                            <span
                                v-if="item.badge && app[item.badge] > 0"
                                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar-accent px-1.5 text-[11px] font-bold text-sidebar"
                                :class="collapsed && 'absolute top-1 right-1 h-4 min-w-4 px-1 text-[10px]'"
                            >
                                {{ app[item.badge] > 99 ? '99+' : app[item.badge] }}
                            </span>
                        </RouterLink>
                    </li>
                </ul>
            </div>
        </div>

        <a
            :href="app.siteUrl"
            target="_blank"
            rel="noopener"
            class="m-3 flex h-10 items-center gap-3 rounded-lg border border-white/10 px-3 text-[13px] font-medium hover:bg-white/5 hover:text-white"
            :class="{ 'justify-center px-0': collapsed }"
            :title="collapsed ? 'View website' : undefined"
        >
            <ExternalLink class="size-4 shrink-0" />
            <span v-if="!collapsed">View website</span>
        </a>
    </nav>
</template>
