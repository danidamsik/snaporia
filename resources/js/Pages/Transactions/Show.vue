<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import DataTable from '@/Components/DataTable.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import IconButton from '@/Components/IconButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    transaction: {
        type: Object,
        required: true,
    },
    backUrl: {
        type: String,
        required: true,
    },
});

const columns = [
    { key: 'filename', label: 'Foto' },
    { key: 'meta', label: 'Meta' },
    { key: 'price', label: 'Harga' },
];
const confirmingDelete = ref(false);
const deleteProcessing = ref(false);

const confirmDeleteTransaction = () => {
    confirmingDelete.value = true;
};

const closeDeleteModal = () => {
    if (!deleteProcessing.value) {
        confirmingDelete.value = false;
    }
};

const deleteTransaction = () => {
    deleteProcessing.value = true;

    router.delete(props.transaction.delete_url, {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            confirmingDelete.value = false;
        },
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
              month: 'long',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Detail Transaksi</h1>
                <Breadcrumbs :items="[{ label: 'Dashboard', href: route('dashboard') }, { label: 'Transaksi', href: backUrl }, { label: 'Detail Transaksi' }]" />
            </div>
        </template>

        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link :href="backUrl" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary-hover">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Kembali
                </Link>
                <IconButton
                    v-if="transaction.can_delete"
                    label="Hapus transaksi"
                    variant="danger"
                    @click="confirmDeleteTransaction"
                >
                    <Trash2 class="h-4 w-4" aria-hidden="true" />
                </IconButton>
            </div>

            <section class="grid gap-5 lg:grid-cols-[1fr_360px]">
                <div class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm text-ink-muted">Order</p>
                            <h2 class="mt-1 font-heading text-2xl font-bold text-ink">{{ transaction.order.order_code }}</h2>
                            <p class="mt-2 text-sm text-ink-muted">{{ transaction.event.name }}</p>
                        </div>
                        <StatusBadge :value="transaction.order.status" />
                    </div>

                    <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-md border border-border bg-surface p-3">
                            <p class="text-ink-muted">Pembeli</p>
                            <p class="mt-1 font-semibold text-ink">{{ transaction.user.name }}</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ transaction.user.email }}</p>
                        </div>
                        <div v-if="transaction.admin" class="rounded-md border border-border bg-surface p-3">
                            <p class="text-ink-muted">Admin</p>
                            <p class="mt-1 font-semibold text-ink">{{ transaction.admin.name }}</p>
                        </div>
                        <div class="rounded-md border border-border bg-surface p-3">
                            <p class="text-ink-muted">Tipe order</p>
                            <p class="mt-1 font-semibold text-ink">{{ transaction.order.type === 'package' ? 'Paket Event' : 'Foto Satuan' }}</p>
                        </div>
                        <div class="rounded-md border border-border bg-surface p-3">
                            <p class="text-ink-muted">Paid at</p>
                            <p class="mt-1 font-semibold text-ink">{{ formatDateTime(transaction.order.paid_at) }}</p>
                        </div>
                    </div>
                </div>

                <aside class="rounded-lg border border-border bg-white p-5 shadow-sm lg:self-start">
                    <h2 class="font-heading text-lg font-semibold text-ink">Pembayaran</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-muted">Gross amount</span>
                            <span class="font-semibold text-ink">{{ formatCurrency(transaction.gross_amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-muted">Total order</span>
                            <span class="font-semibold text-ink">{{ formatCurrency(transaction.order.total_amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-muted">Midtrans status</span>
                            <span class="font-semibold text-ink">{{ transaction.status }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-muted">Payment type</span>
                            <span class="font-semibold text-ink">{{ transaction.payment_type || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-muted">Fraud status</span>
                            <span class="font-semibold text-ink">{{ transaction.fraud_status || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-muted">Expired</span>
                            <span class="font-semibold text-ink">{{ formatDateTime(transaction.expires_at) }}</span>
                        </div>
                    </div>

                </aside>
            </section>

            <section class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-heading text-lg font-semibold text-ink">Item Pembelian</h2>
                <DataTable :columns="columns" :rows="transaction.items">
                    <template #cell-filename="{ row }">
                        <p class="font-semibold text-ink">{{ row.filename }}</p>
                    </template>
                    <template #cell-meta="{ row }">
                        <p class="text-sm text-ink-muted">{{ row.mime_type }} - {{ formatFileSize(row.file_size) }}</p>
                    </template>
                    <template #cell-price="{ row }">
                        <p class="font-semibold text-ink">{{ formatCurrency(row.price) }}</p>
                    </template>
                </DataTable>
            </section>
        </div>

        <DeleteConfirmationModal
            :show="confirmingDelete"
            title="Hapus transaksi?"
            :message="`Transaksi ${transaction.midtrans_order_id} akan dihapus dari riwayat monitoring.`"
            confirm-label="Hapus Transaksi"
            :processing="deleteProcessing"
            @close="closeDeleteModal"
            @confirm="deleteTransaction"
        />
    </AuthenticatedLayout>
</template>
