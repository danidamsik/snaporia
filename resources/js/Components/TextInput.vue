<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="min-h-10 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-surface disabled:text-ink-muted"
        v-model="model"
        ref="input"
    />
</template>
