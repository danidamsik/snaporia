<script setup>
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Download, FileText, Image, MapPin, ShoppingCart } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    items: {
        type: Object,
        required: true,
    },
});

const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);

const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(new Date(value))
        : '-';

const formatFileSize = (bytes) => {
    if (!bytes) return '-';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let index = 0;

    while (size >= 1024 && index < units.length - 1) {
        size /= 1024;
        index++;
    }

    return `${size.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
};

const typeLabel = (type) => (type === 'package' ? 'Paket Event' : 'Foto Satuan');
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-heading text-xl font-semibold text-ink">Detail Order</h1>
        </template>

        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link :href="route('visitor.orders.index')" class="text-sm font-semibold text-primary hover:text-primary-hover">
                    Riwayat Pembelian
                </Link>
                <StatusBadge :value="order.status" />
            </div>

            <section class="grid gap-5 lg:grid-cols-[1fr_340px]">
                <div class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm text-ink-muted">Kode order</p>
                            <h2 class="mt-1 font-heading text-2xl font-bold text-ink">{{ order.order_code }}</h2>
                            <Link :href="order.event.url" class="mt-2 block text-base font-semibold text-primary hover:text-primary-hover">
                                {{ order.event.name }}
                            </Link>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-sm text-ink-muted">Total</p>
                            <p class="mt-1 text-2xl font-bold text-primary">{{ formatCurrency(order.total_amount) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 text-sm text-ink-muted sm:grid-cols-2 xl:grid-cols-4">
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <FileText class="h-4 w-4" aria-hidden="true" />
                            {{ typeLabel(order.type) }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <Image class="h-4 w-4" aria-hidden="true" />
                            {{ order.items_count }} foto
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <CalendarDays class="h-4 w-4" aria-hidden="true" />
                            {{ formatDate(order.paid_at || order.created_at) }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <MapPin class="h-4 w-4" aria-hidden="true" />
                            {{ order.event.location || 'Lokasi menyusul' }}
                        </span>
                    </div>
                </div>

                <aside class="rounded-lg border border-border bg-white p-5 shadow-sm lg:self-start">
                    <h2 class="font-heading text-lg font-semibold text-ink">Akses Download</h2>
                    <p v-if="order.status === 'paid'" class="mt-2 text-sm text-ink-muted">
                        File original tersedia pada daftar foto di bawah.
                    </p>
                    <p v-else class="mt-2 text-sm text-ink-muted">
                        Download tersedia setelah pembayaran berhasil.
                    </p>
                    <Link
                        v-if="order.status !== 'paid'"
                        :href="order.event.url"
                        class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <ShoppingCart class="h-4 w-4" aria-hidden="true" />
                        Beli Ulang
                    </Link>
                </aside>
            </section>

            <section class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-heading text-lg font-semibold text-ink">Daftar Foto</h2>
                        <p class="text-sm text-ink-muted">{{ items.total }} foto dalam order</p>
                    </div>
                </div>

                <div v-if="items.data.length" class="divide-y divide-border">
                    <article v-for="item in items.data" :key="item.id" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ item.filename }}</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ item.mime_type }} · {{ formatFileSize(item.file_size) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center justify-between gap-3 sm:min-w-56">
                            <p class="text-sm font-semibold text-ink">{{ formatCurrency(item.price) }}</p>
                            <a
                                v-if="item.download_url"
                                :href="item.download_url"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                <Download class="h-4 w-4" aria-hidden="true" />
                                Download
                            </a>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink-muted disabled:cursor-not-allowed"
                            >
                                <Download class="h-4 w-4" aria-hidden="true" />
                                Download
                            </button>
                        </div>
                    </article>
                </div>

                <EmptyState v-else title="Foto tidak ditemukan" message="Order ini belum memiliki item foto.">
                    <template #icon>
                        <Image class="h-6 w-6" aria-hidden="true" />
                    </template>
                </EmptyState>

                <Pagination class="mt-5" :links="items.links" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
