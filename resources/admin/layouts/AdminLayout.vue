<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useLocalStorage } from '@vueuse/core';
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, VisuallyHidden } from 'reka-ui';
import AppSidebar from '@admin/components/layout/AppSidebar.vue';
import AppTopbar from '@admin/components/layout/AppTopbar.vue';

const collapsed = useLocalStorage('admin.sidebar.collapsed', false);
const mobileOpen = ref(false);
const route = useRoute();

watch(() => route.fullPath, () => (mobileOpen.value = false));
</script>

<template>
    <div class="min-h-dvh">
        <!-- Desktop sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 hidden transition-[width] duration-200 lg:block"
            :class="collapsed ? 'w-[76px]' : 'w-[264px]'"
        >
            <AppSidebar :collapsed="collapsed" />
        </aside>

        <!-- Mobile drawer -->
        <DialogRoot v-model:open="mobileOpen">
            <DialogPortal>
                <DialogOverlay class="fixed inset-0 z-50 bg-[#0b1a2c]/50 lg:hidden" />
                <DialogContent class="fixed inset-y-0 left-0 z-50 w-[280px] max-w-[85vw] shadow-[var(--shadow-pop)] outline-none lg:hidden">
                    <VisuallyHidden><DialogTitle>Navigation</DialogTitle></VisuallyHidden>
                    <AppSidebar @navigate="mobileOpen = false" />
                </DialogContent>
            </DialogPortal>
        </DialogRoot>

        <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-[264px]'">
            <AppTopbar :collapsed="collapsed" @toggle-collapse="collapsed = !collapsed" @open-menu="mobileOpen = true" />
            <main class="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <RouterView v-slot="{ Component }">
                    <Transition
                        mode="out-in"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-active-class="transition duration-150 ease-out"
                        leave-to-class="opacity-0"
                        leave-active-class="transition duration-100 ease-in"
                    >
                        <component :is="Component" :key="String(route.name)" />
                    </Transition>
                </RouterView>
            </main>
        </div>
    </div>
</template>
