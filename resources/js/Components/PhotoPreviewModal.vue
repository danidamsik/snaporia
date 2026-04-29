<script setup>
import { CheckCircle2, Download, ShoppingCart, X } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

defineProps({
    photo: {
        type: Object,
        default: null,
    },
    price: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close']);

const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="photo"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
            role="dialog"
            aria-modal="true"
            @click.self="emit('close')"
        >
            <div class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ photo.filename }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ photo.is_purchased ? 'Foto original tersedia' : 'Preview watermark' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-border text-ink-muted transition hover:bg-surface hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        aria-label="Tutup preview"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 bg-surface">
                    <img
                        :src="photo.preview_url ?? photo.watermarked_url"
                        :alt="photo.filename"
                        class="mx-auto max-h-[70vh] w-full object-contain"
                    />
                </div>

                <div class="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">{{ formatCurrency(price) }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ photo.is_purchased ? 'Download akan mengambil file original.' : 'Download akan mengambil file watermark.' }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a
                            :href="photo.download_url"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            download
                        >
                            <Download class="h-4 w-4" aria-hidden="true" />
                            Download
                        </a>
                        <span
                            v-if="photo.is_purchased"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink-muted"
                        >
                            <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                            Sudah dibeli
                        </span>
                        <Link
                            v-else
                            :href="photo.checkout_url"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <ShoppingCart class="h-4 w-4" aria-hidden="true" />
                            Beli
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
