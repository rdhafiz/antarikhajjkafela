<script setup lang="ts">
/** A small mock of the website using the chosen brand colors. */
defineProps<{ primary: string; secondary: string; accent: string }>();
</script>

<template>
    <div class="overflow-hidden rounded-xl ring-1 ring-line" aria-hidden="true">
        <div class="flex items-center justify-between px-4 py-3" :style="{ backgroundColor: primary }">
            <span class="h-2.5 w-20 rounded-full bg-white/80" />
            <span class="flex gap-2">
                <span class="h-2 w-8 rounded-full bg-white/40" />
                <span class="h-2 w-8 rounded-full bg-white/40" />
                <span class="h-2 w-8 rounded-full bg-white/40" />
            </span>
        </div>
        <div class="space-y-3 bg-white p-4">
            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold text-white" :style="{ backgroundColor: accent }">Hajj 2027</span>
            <p class="text-[15px] font-bold" :style="{ color: primary }">Premium Hajj Package</p>
            <p class="text-[12px] text-ink-soft">Five-star hotels near the Haram, guided by experienced scholars.</p>
            <div class="flex gap-2">
                <span class="rounded-lg px-3 py-1.5 text-[12px] font-bold" :style="{ backgroundColor: secondary, color: primary }">Book now</span>
                <span class="rounded-lg border px-3 py-1.5 text-[12px] font-bold" :style="{ borderColor: primary, color: primary }">Details</span>
            </div>
        </div>
    </div>
</template>
