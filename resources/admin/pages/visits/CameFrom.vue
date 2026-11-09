<script setup lang="ts">
import type { VisitLanding } from './types';

/** A "Came from" row for a `dl`: channel, source and the page they landed on. */
defineProps<{ visit: VisitLanding }>();
</script>

<template>
    <dt>Came from</dt>
    <dd class="min-w-0 [overflow-wrap:anywhere]">
        {{ visit.channel_label ?? visit.channel }}<template v-if="visit.source"> · {{ visit.source }}</template>
        <a :href="visit.landing.url" target="_blank" rel="noopener" class="block text-[13px] text-brand hover:underline">{{ visit.landing.title ?? visit.landing.url }}</a>
    </dd>
</template>
