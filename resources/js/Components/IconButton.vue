<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'secondary',
    },
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        :aria-label="label"
        :title="label"
        class="inline-flex h-9 w-9 items-center justify-center rounded-md border text-sm transition focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="{
            'border-border bg-white text-ink hover:bg-surface': variant === 'secondary',
            'border-transparent bg-primary text-white hover:bg-primary-hover': variant === 'primary',
            'border-transparent bg-red-600 text-white hover:bg-red-700': variant === 'danger',
        }"
    >
        <slot />
        <span class="sr-only">{{ label }}</span>
    </button>
</template>
