<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex min-h-56 flex-col items-center justify-center rounded-lg border border-dashed border-border bg-white px-6 py-10 text-center">
        <div class="mb-4 grid h-12 w-12 place-items-center rounded-full bg-indigo-50 text-primary">
            <slot name="icon" />
        </div>
        <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
        <p v-if="message" class="mt-2 max-w-md text-sm text-ink-muted">{{ message }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
