<script setup lang="ts">
import { computed } from 'vue';
import { readableOn } from './meta';

/** A small mock of the website in the chosen brand colours (text colours follow for contrast). */
const props = defineProps<{ primary: string; secondary: string; accent: string }>();

const safe = (hex: string, fallback: string) => (/^#[0-9a-f]{6}$/i.test(hex) ? hex : fallback);
const c = computed(() => {
    const primary = safe(props.primary, '#012a51');
    const secondary = safe(props.secondary, '#fdc802');
    const accent = safe(props.accent, '#01914d');
    return { primary, secondary, accent, onPrimary: readableOn(primary), onSecondary: readableOn(secondary), onAccent: readableOn(accent) };
});
</script>

<template>
    <figure class="tp overflow-hidden rounded-2xl bg-white ring-1 ring-line" aria-label="Preview of the website colours">
        <!-- Header + hero in the primary colour -->
        <div class="relative overflow-hidden px-5 pt-4 pb-6" :style="{ backgroundColor: c.primary, color: c.onPrimary }">
            <span class="tp-ring absolute -top-10 -right-8 size-36 rounded-full" :style="{ borderColor: c.secondary }" aria-hidden="true" />
            <div class="relative flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <span class="size-5 rounded-md" :style="{ backgroundColor: c.secondary }" />
                    <span class="text-[12px] font-bold tracking-wide">Antarik</span>
                </span>
                <span class="flex gap-3 text-[11px] font-medium"><span>Hajj</span><span>Umrah</span><span>Contact</span></span>
            </div>
            <p class="relative mt-5 text-[11px] font-semibold tracking-[0.14em] uppercase" :style="{ color: c.secondary }">Hajj 2027</p>
            <p class="relative mt-1 text-[19px] leading-tight font-bold">Journey of a lifetime</p>
            <span class="relative mt-4 inline-flex rounded-lg px-3.5 py-2 text-[12px] font-bold" :style="{ backgroundColor: c.secondary, color: c.onSecondary }">Book now</span>
        </div>

        <!-- Package card -->
        <div class="space-y-2.5 p-5">
            <div class="flex items-center gap-2">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold" :style="{ backgroundColor: c.accent, color: c.onAccent }">Featured</span>
                <span class="text-[11px] font-semibold text-ink-soft">40 days</span>
            </div>
            <p class="text-[15px] font-bold" :style="{ color: c.primary }">Premium Hajj Package</p>
            <p class="text-[12.5px] leading-relaxed text-ink-soft">Five-star hotels near the Haram, guided by experienced scholars.</p>
            <div class="flex items-center justify-between pt-1">
                <span class="text-[15px] font-bold text-ink">৳ 9,50,000</span>
                <span class="rounded-lg border px-3 py-1.5 text-[12px] font-bold" :style="{ borderColor: c.primary, color: c.primary }">Details</span>
            </div>
        </div>
        <figcaption class="border-t border-line bg-canvas px-5 py-2.5 text-[12px] text-ink-soft">Live preview. Text switches between light and dark to stay readable.</figcaption>
    </figure>
</template>

<style scoped>
.tp-ring {
    border-width: 14px;
    border-style: solid;
    opacity: 0.18;
}

.tp > div,
.tp span {
    transition: background-color 300ms ease, color 300ms ease, border-color 300ms ease;
}
</style>
