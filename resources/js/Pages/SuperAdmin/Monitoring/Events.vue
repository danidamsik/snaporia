<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, Search } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormSelect from '@/Components/FormSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    events: {
        type: Object,
        required: true,
    },
    admins: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            admin_id: '',
            status: '',
            date_from: '',
            date_to: '',
            q: '',
        }),
    },
});

const columns = [
    { key: 'event', label: 'Event' },
    { key: 'admin', label: 'Admin' },
    { key: 'status', label: 'Status' },
    { key: 'price', label: 'Harga' },
    { key: 'stats', label: 'Data' },
    { key: 'actions', label: 'Aksi' },
];

const form = useForm({
    admin_id: props.filters.admin_id ?? '',
    status: props.filters.status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    q: props.filters.q ?? '',
});

const submit = () => {
    form.get(route('super-admin.events.index'), {
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

const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(value))
        : 'Tanggal kosong';
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Monitoring Event</h1>
                <Breadcrumbs :items="[{ label: 'Dashboard', href: route('dashboard') }, { label: 'Monitoring Event' }]" />
            </div>
        </template>

        <div class="space-y-5">
            <form
                class="grid grid-cols-1 gap-3 rounded-lg border border-border bg-white p-4 md:grid-cols-3"
                @submit.prevent="submit"
            >
                <TextInput
                    v-model="form.q"
                    type="search"
                    maxlength="100"
                    placeholder="Cari event, lokasi, tanggal, atau file"
                />
                <FormSelect
                    v-model="form.admin_id"
                    :options="[{ label: 'Semua admin', value: '' }, ...admins.map((admin) => ({ label: admin.name, value: admin.id }))]"
                />
                <FormSelect
                    v-model="form.status"
                    :options="[
                        { label: 'Semua status', value: '' },
                        { label: 'Published', value: 'published' },
                        { label: 'Draft', value: 'draft' },
                    ]"
                />
                <input
                    v-model="form.date_from"
                    type="date"
                    class="min-h-10 w-full min-w-0 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                    aria-label="Tanggal awal"
                />
                <input
                    v-model="form.date_to"
                    type="date"
                    class="min-h-10 w-full min-w-0 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                    aria-label="Tanggal akhir"
                />
                <SecondaryButton
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                >
                    <Search class="h-4 w-4" aria-hidden="true" />
                    Filter
                </SecondaryButton>
            </form>

            <DataTable :columns="columns" :rows="events.data">
                <template #empty>
                    <EmptyState title="Belum ada event" message="Event dari fotografer akan muncul di sini setelah dibuat." />
                </template>

                <template #cell-event="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.name }}</p>
                        <p class="mt-1 text-sm text-ink-muted">{{ formatDate(row.date) }} - {{ row.location || 'Lokasi kosong' }}</p>
                    </div>
                </template>

                <template #cell-admin="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.admin.name }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ row.admin.email }}</p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :value="row.is_published ? 'published' : 'draft'" />
                </template>

                <template #cell-price="{ row }">
                    <div class="text-sm">
                        <p class="font-semibold text-ink">{{ formatCurrency(row.price_per_photo) }}</p>
                        <p class="mt-1 text-ink-muted">Paket {{ formatCurrency(row.price_package) }}</p>
                    </div>
                </template>

                <template #cell-stats="{ row }">
                    <div class="text-sm text-ink-muted">
                        <p>{{ row.photos_count }} foto</p>
                        <p>{{ row.orders_count }} order</p>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <Link
                        v-if="row.public_url"
                        :href="row.public_url"
                        aria-label="Lihat event publik"
                        title="Lihat event publik"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white text-ink transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <ExternalLink class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only">Lihat event publik</span>
                    </Link>
                    <span v-else class="text-sm text-ink-muted">Draft</span>
                </template>
            </DataTable>

            <Pagination :links="events.links" />
        </div>
    </AuthenticatedLayout>
</template>
