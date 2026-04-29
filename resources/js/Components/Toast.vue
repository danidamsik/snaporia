<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle, Info, TriangleAlert, X, XCircle } from 'lucide-vue-next';
import IconButton from '@/Components/IconButton.vue';

const page = usePage();
const toasts = ref([]);

const icons = {
    success: CheckCircle,
    error: XCircle,
    warning: TriangleAlert,
    info: Info,
};

const styles = {
    success: 'border-green-200 bg-green-50 text-green-800',
    error: 'border-red-200 bg-red-50 text-red-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    info: 'border-cyan-200 bg-cyan-50 text-cyan-800',
};
const toastFlashTypes = ['success', 'error', 'warning', 'info'];

const addToast = (detail) => {
    const id = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
    const toast = {
        id,
        type: detail.type ?? 'info',
        title: detail.title ?? detail.message ?? 'Informasi',
        message: detail.message && detail.title ? detail.message : '',
        duration: detail.duration ?? 4000,
    };

    toasts.value.push(toast);

    if (toast.duration > 0) {
        setTimeout(() => {
            toasts.value = toasts.value.filter((item) => item.id !== id);
        }, toast.duration);
    }
};

const handleToastEvent = (event) => addToast(event.detail ?? {});

const flashEntries = computed(() =>
    toastFlashTypes
        .map((type) => [type, page.props.flash?.[type]])
        .filter(([, value]) => Boolean(value))
);

watch(
    flashEntries,
    (entries) => {
        entries.forEach(([type, message]) => addToast({ type, title: message }));
    },
    { immediate: true }
);

onMounted(() => window.addEventListener('snaporia:toast', handleToastEvent));
onUnmounted(() => window.removeEventListener('snaporia:toast', handleToastEvent));
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3">
        <TransitionGroup
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex gap-3 rounded-lg border p-4 shadow-lg"
                :class="styles[toast.type] ?? styles.info"
                role="status"
            >
                <component :is="icons[toast.type] ?? Info" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">{{ toast.title }}</p>
                    <p v-if="toast.message" class="mt-1 text-sm opacity-80">{{ toast.message }}</p>
                </div>
                <IconButton
                    label="Tutup notifikasi"
                    class="h-7 w-7 border-transparent bg-transparent shadow-none hover:bg-white/50"
                    @click="toasts = toasts.filter((item) => item.id !== toast.id)"
                >
                    <X class="h-4 w-4" aria-hidden="true" />
                </IconButton>
            </div>
        </TransitionGroup>
    </div>
</template>
