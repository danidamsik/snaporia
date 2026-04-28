<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
});

const variants = {
    published: 'bg-green-50 text-green-700 ring-green-600/20',
    draft: 'bg-gray-100 text-gray-700 ring-gray-500/20',
    unpublished: 'bg-gray-100 text-gray-700 ring-gray-500/20',
    pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    paid: 'bg-green-50 text-green-700 ring-green-600/20',
    failed: 'bg-red-50 text-red-700 ring-red-600/20',
    expired: 'bg-gray-100 text-gray-700 ring-gray-500/20',
    active: 'bg-green-50 text-green-700 ring-green-600/20',
    inactive: 'bg-gray-100 text-gray-700 ring-gray-500/20',
    ready: 'bg-green-50 text-green-700 ring-green-600/20',
    watermark_failed: 'bg-red-50 text-red-700 ring-red-600/20',
    super_admin: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    admin: 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
    visitor: 'bg-amber-50 text-amber-700 ring-amber-600/20',
};

const label = computed(() => props.value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()));
const classes = computed(() => variants[props.value] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20');
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="classes">
        {{ label }}
    </span>
</template>
