<script setup>
import { AlertTriangle } from 'lucide-vue-next';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Hapus data?',
    },
    message: {
        type: String,
        default: 'Data yang dihapus tidak bisa dikembalikan.',
    },
    confirmLabel: {
        type: String,
        default: 'Hapus',
    },
    cancelLabel: {
        type: String,
        default: 'Batal',
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="!processing" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-red-50 text-red-600">
                    <AlertTriangle class="h-5 w-5" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h2 class="font-heading text-lg font-semibold text-ink">{{ title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-ink-muted">{{ message }}</p>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" :disabled="processing" @click="emit('close')">
                    {{ cancelLabel }}
                </SecondaryButton>
                <DangerButton type="button" :disabled="processing" @click="emit('confirm')">
                    {{ processing ? 'Menghapus...' : confirmLabel }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
