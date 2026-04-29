<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav class="mt-1 flex min-w-0 items-center gap-1 text-xs font-semibold text-ink-muted" aria-label="Breadcrumb">
        <template v-for="(item, index) in items" :key="`${item.label}-${index}`">
            <ChevronRight v-if="index > 0" class="h-3.5 w-3.5 shrink-0 text-ink-muted/70" aria-hidden="true" />
            <Link
                v-if="item.href && index < items.length - 1"
                :href="item.href"
                class="truncate hover:text-primary"
            >
                {{ item.label }}
            </Link>
            <span v-else class="truncate" :class="index === items.length - 1 ? 'text-ink' : ''">
                {{ item.label }}
            </span>
        </template>
    </nav>
</template>
