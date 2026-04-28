<script setup>
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Camera, Download, Eye, FileText, MapPin } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    orders: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    emptyMessage: {
        type: String,
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
              month: 'short',
              year: 'numeric',
          }).format(new Date(value))
        : '-';

const typeLabel = (type) => (type === 'package' ? 'Paket Event' : 'Foto Satuan');
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-heading text-xl font-semibold text-ink">{{ title }}</h1>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-ink-muted">{{ orders.total }} order ditemukan</p>
                </div>
                <Link
                    :href="route('events.index')"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <Camera class="h-4 w-4" aria-hidden="true" />
                    Jelajah Event
                </Link>
            </div>

            <div v-if="orders.data.length" class="overflow-hidden rounded-lg border border-border bg-white shadow-sm">
                <div class="divide-y divide-border">
                    <article v-for="order in orders.data" :key="order.id" class="p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-ink">{{ order.order_code }}</h2>
                                    <StatusBadge :value="order.status" />
                                </div>
                                <Link :href="order.url" class="mt-1 block truncate text-sm font-semibold text-primary hover:text-primary-hover">
                                    {{ order.event.name }}
                                </Link>
                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-ink-muted">
                                    <span class="inline-flex items-center gap-1.5">
                                        <FileText class="h-3.5 w-3.5" aria-hidden="true" />
                                        {{ typeLabel(order.type) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <Download class="h-3.5 w-3.5" aria-hidden="true" />
                                        {{ order.items_count }} foto
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <CalendarDays class="h-3.5 w-3.5" aria-hidden="true" />
                                        {{ formatDate(order.paid_at || order.created_at) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5" aria-hidden="true" />
                                        {{ order.event.location || 'Lokasi menyusul' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center justify-between gap-3 lg:min-w-64">
                                <p class="text-right text-base font-bold text-ink">{{ formatCurrency(order.total_amount) }}</p>
                                <Link
                                    :href="order.url"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                >
                                    <Eye class="h-4 w-4" aria-hidden="true" />
                                    Detail
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <EmptyState v-else :title="title + ' kosong'" :message="emptyMessage">
                <template #icon>
                    <FileText class="h-6 w-6" aria-hidden="true" />
                </template>
                <template #action>
                    <Link
                        :href="route('events.index')"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <Camera class="h-4 w-4" aria-hidden="true" />
                        Jelajah Event
                    </Link>
                </template>
            </EmptyState>

            <Pagination :links="orders.links" />
        </div>
    </AuthenticatedLayout>
</template>
