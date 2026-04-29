<script setup>
const model = defineModel({
    type: [String, Number, Boolean],
    default: '',
});

defineProps({
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Pilih data',
    },
});
</script>

<template>
    <select
        v-model="model"
        class="min-h-10 w-full min-w-0 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-surface disabled:text-ink-muted"
    >
        <option value="" disabled>{{ placeholder }}</option>
        <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
        </option>
    </select>
</template>
