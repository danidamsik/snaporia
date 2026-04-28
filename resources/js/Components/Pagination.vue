<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length" class="flex flex-wrap items-center gap-2" aria-label="Pagination">
        <component
            :is="link.url ? Link : 'span'"
            v-for="link in links"
            :key="link.label"
            :href="link.url || undefined"
            preserve-scroll
            class="rounded-md border px-3 py-2 text-sm"
            :class="[
                link.active
                    ? 'border-primary bg-primary text-white'
                    : 'border-border bg-white text-ink hover:bg-surface',
                !link.url && 'cursor-not-allowed opacity-50',
            ]"
            v-html="link.label"
        />
    </nav>
</template>
