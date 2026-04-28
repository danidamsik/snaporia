<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { Eye, Search, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormSelect from '@/Components/FormSelect.vue';
import IconButton from '@/Components/IconButton.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    transactions: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        default: () => [],
    },
    admins: {
        type: Array,
        default: () => [],
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
    routeBase: {
        type: String,
        required: true,
    },
});

const columns = [
    { key: 'transaction', label: 'Transaksi' },
    { key: 'order', label: 'Order' },
    { key: 'event', label: 'Event' },
    { key: 'status', label: 'Status' },
    { key: 'amount', label: 'Nominal' },
    { key: 'actions', label: 'Aksi' },
];

const form = useForm({
    status: props.filters.status ?? '',
    event_id: props.filters.event_id ?? '',
    admin_id: props.filters.admin_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    q: props.filters.q ?? '',
});

const statusOptions = [
    { label: 'Semua status', value: '' },
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
    { label: 'Failed', value: 'failed' },
    { label: 'Expired', value: 'expired' },
];

const submit = () => {
    form.get(route(`${props.routeBase}.index`), {
        preserveState: true,
        preserveScroll: true,
    });
};

const deleteTransaction = (transaction) => {
    if (!confirm(`Hapus transaksi ${transaction.midtrans_order_id}?`)) {
        return;
    }

    router.delete(transaction.delete_url, { preserveScroll: true });
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
                <h1 class="font-heading text-xl font-semibold text-ink">{{ title }}</h1>
                <p class="mt-1 text-sm text-ink-muted">Pantau status pembayaran, order, event, dan pembeli.</p>
            </div>
        </template>

        <div class="space-y-5">
            <form class="grid gap-3 rounded-lg border border-border bg-white p-4 lg:grid-cols-[1fr_180px_180px_160px_160px_auto]" @submit.prevent="submit">
                <TextInput v-model="form.q" type="search" maxlength="100" placeholder="Cari order atau Midtrans ID" />
                <FormSelect v-model="form.status" :options="statusOptions" />
                <FormSelect
                    v-model="form.event_id"
                    :options="[{ label: 'Semua event', value: '' }, ...events.map((event) => ({ label: event.name, value: event.id }))]"
                />
                <FormSelect
                    v-if="admins.length"
                    v-model="form.admin_id"
                    :options="[{ label: 'Semua admin', value: '' }, ...admins.map((admin) => ({ label: admin.name, value: admin.id }))]"
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

            <DataTable :columns="columns" :rows="transactions.data">
                <template #empty>
                    <EmptyState title="Belum ada transaksi" message="Transaksi akan muncul setelah pembeli membuat pembayaran." />
                </template>

                <template #cell-transaction="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.midtrans_order_id }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ row.midtrans_transaction_id || 'Belum ada transaction ID' }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ formatDateTime(row.created_at) }}</p>
                    </div>
                </template>

                <template #cell-order="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.order.order_code }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ row.user.name }} - {{ row.user.email }}</p>
                    </div>
                </template>

                <template #cell-event="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.event.name }}</p>
                        <p v-if="row.admin" class="mt-1 text-xs text-ink-muted">{{ row.admin.name }}</p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="space-y-2">
                        <StatusBadge :value="row.order_status" />
                        <p class="text-xs text-ink-muted">Midtrans: {{ row.status }}</p>
                    </div>
                </template>

                <template #cell-amount="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ formatCurrency(row.gross_amount) }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ row.payment_type || 'Metode belum dipilih' }}</p>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <Link
                            :href="row.url"
                            aria-label="Lihat transaksi"
                            title="Lihat transaksi"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white text-ink transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <Eye class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">Lihat transaksi</span>
                        </Link>
                        <IconButton v-if="canDelete && row.can_delete" label="Hapus transaksi" variant="danger" @click="deleteTransaction(row)">
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                        </IconButton>
                    </div>
                </template>
            </DataTable>

            <Pagination :links="transactions.links" />
        </div>
    </AuthenticatedLayout>
</template>
