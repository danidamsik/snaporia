<script setup>
import { useForm } from '@inertiajs/vue3';
import { Banknote, FileText, Image, Search } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormSelect from '@/Components/FormSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        required: true,
    },
});

const columns = [
    { key: 'order_code', label: 'Order' },
    { key: 'event', label: 'Event' },
    { key: 'items_count', label: 'Foto' },
    { key: 'paid_at', label: 'Paid At' },
    { key: 'total_amount', label: 'Pendapatan' },
];

const form = useForm({
    event_id: props.filters.event_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const submit = () => {
    form.get(route('admin.reports.sales'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);

const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : '-';
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Laporan Penjualan</h1>
                <p class="mt-1 text-sm text-ink-muted">Pendapatan dihitung dari total order paid.</p>
            </div>
        </template>

        <div class="space-y-5">
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-md bg-green-50 text-green-700">
                            <Banknote class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="text-sm text-ink-muted">Total pendapatan</p>
                            <p class="text-xl font-bold text-ink">{{ formatCurrency(summary.total_revenue) }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-border bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-md bg-indigo-50 text-primary">
                            <FileText class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="text-sm text-ink-muted">Order paid</p>
                            <p class="text-xl font-bold text-ink">{{ summary.paid_orders_count }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-border bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-md bg-cyan-50 text-cyan-700">
                            <Image class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="text-sm text-ink-muted">Foto terjual</p>
                            <p class="text-xl font-bold text-ink">{{ summary.photos_sold_count }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <form class="grid gap-3 rounded-lg border border-border bg-white p-4 lg:grid-cols-[260px_180px_180px_auto]" @submit.prevent="submit">
                <FormSelect
                    v-model="form.event_id"
                    :options="[{ label: 'Semua event', value: '' }, ...events.map((event) => ({ label: event.name, value: event.id }))]"
                />
                <input
                    v-model="form.date_from"
                    type="date"
                    class="min-h-10 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                    aria-label="Tanggal awal"
                />
                <input
                    v-model="form.date_to"
                    type="date"
                    class="min-h-10 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                    aria-label="Tanggal akhir"
                />
                <SecondaryButton type="submit" :disabled="form.processing">
                    <Search class="h-4 w-4" aria-hidden="true" />
                    Filter
                </SecondaryButton>
            </form>

            <DataTable :columns="columns" :rows="orders.data">
                <template #empty>
                    <EmptyState title="Belum ada penjualan" message="Order paid akan muncul di laporan ini." />
                </template>
                <template #cell-order_code="{ row }">
                    <p class="font-semibold text-ink">{{ row.order_code }}</p>
                    <p class="mt-1 text-xs text-ink-muted">{{ row.type === 'package' ? 'Paket Event' : 'Foto Satuan' }}</p>
                </template>
                <template #cell-event="{ row }">
                    <p class="font-semibold text-ink">{{ row.event.name }}</p>
                </template>
                <template #cell-items_count="{ value }">
                    <p class="text-sm text-ink">{{ value }} foto</p>
                </template>
                <template #cell-paid_at="{ value }">
                    <p class="text-sm text-ink-muted">{{ formatDateTime(value) }}</p>
                </template>
                <template #cell-total_amount="{ value }">
                    <p class="font-semibold text-ink">{{ formatCurrency(value) }}</p>
                </template>
            </DataTable>

            <Pagination :links="orders.links" />
        </div>
    </AuthenticatedLayout>
</template>
