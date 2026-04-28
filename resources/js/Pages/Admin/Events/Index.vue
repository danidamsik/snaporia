<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { Edit, Eye, EyeOff, Plus, Search, Trash2 } from 'lucide-vue-next';
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
    events: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: '', q: '' }),
    },
});

const columns = [
    { key: 'event', label: 'Event' },
    { key: 'status', label: 'Status' },
    { key: 'price', label: 'Harga' },
    { key: 'stats', label: 'Data' },
    { key: 'actions', label: 'Aksi' },
];

const form = useForm({
    status: props.filters.status ?? '',
    q: props.filters.q ?? '',
});

const submit = () => {
    form.get(route('admin.events.index'), {
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

const publishEvent = (event) => {
    router.patch(route('admin.events.publish', event.id), {}, { preserveScroll: true });
};

const unpublishEvent = (event) => {
    router.patch(route('admin.events.unpublish', event.id), {}, { preserveScroll: true });
};

const deleteEvent = (event) => {
    if (!confirm(`Hapus event ${event.name}?`)) {
        return;
    }

    router.delete(route('admin.events.destroy', event.id), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Event Saya</h1>
                <p class="mt-1 text-sm text-ink-muted">Kelola event, harga, dan status publikasi.</p>
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 lg:flex-row lg:items-end lg:justify-between">
                <form class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_180px_auto]" @submit.prevent="submit">
                    <TextInput v-model="form.q" type="search" maxlength="100" placeholder="Cari nama, lokasi, atau tanggal" />
                    <FormSelect
                        v-model="form.status"
                        placeholder="Semua status"
                        :options="[
                            { label: 'Semua status', value: '' },
                            { label: 'Published', value: 'published' },
                            { label: 'Draft', value: 'draft' },
                        ]"
                    />
                    <SecondaryButton type="submit" :disabled="form.processing">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Filter
                    </SecondaryButton>
                </form>

                <Link
                    :href="route('admin.events.create')"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Buat Event
                </Link>
            </div>

            <DataTable :columns="columns" :rows="events.data">
                <template #empty>
                    <EmptyState title="Belum ada event" message="Buat event pertama untuk mulai mengunggah dan menjual foto." />
                </template>

                <template #cell-event="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.name }}</p>
                        <p class="mt-1 text-sm text-ink-muted">{{ formatDate(row.date) }} · {{ row.location || 'Lokasi kosong' }}</p>
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
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="row.can_update"
                            :href="route('admin.events.edit', row.id)"
                            aria-label="Edit event"
                            title="Edit event"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white text-ink transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <Edit class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">Edit event</span>
                        </Link>

                        <IconButton v-if="row.can_update && !row.is_published" label="Publikasikan event" @click="publishEvent(row)">
                            <Eye class="h-4 w-4" aria-hidden="true" />
                        </IconButton>

                        <IconButton v-if="row.can_update && row.is_published" label="Batalkan publikasi" @click="unpublishEvent(row)">
                            <EyeOff class="h-4 w-4" aria-hidden="true" />
                        </IconButton>

                        <IconButton v-if="row.can_delete" label="Hapus event" variant="danger" @click="deleteEvent(row)">
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                        </IconButton>
                    </div>
                </template>
            </DataTable>

            <Pagination :links="events.links" />
        </div>
    </AuthenticatedLayout>
</template>
