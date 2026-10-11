<script setup lang="ts">
import AppToaster from '@admin/components/ui/AppToaster.vue';
import ConfirmHost from '@admin/components/ui/ConfirmHost.vue';
</script>

<template>
    <RouterView />
    <AppToaster />
    <ConfirmHost />
</template>
