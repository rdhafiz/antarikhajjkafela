<script setup lang="ts">
import { SearchX } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
</script>

<template>
    <div class="card">
        <EmptyState :icon="SearchX" title="Page not found" description="This page doesn't exist, or you don't have access to it.">
            <AppButton to="/admin" variant="secondary">Back to dashboard</AppButton>
        </EmptyState>
    </div>
</template>
