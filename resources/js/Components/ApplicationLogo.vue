<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const appName = computed(() => page.props.app?.name ?? 'Snaporia');
const appInitial = computed(() => appName.value.trim().charAt(0).toUpperCase() || 'S');
</script>

<template>
    <div class="inline-flex items-center gap-2 text-primary" :aria-label="appName">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-primary text-white">
            <span class="font-heading text-base font-bold">{{ appInitial }}</span>
        </div>
        <span class="font-heading text-lg font-bold text-ink">{{ appName }}</span>
    </div>
</template>
