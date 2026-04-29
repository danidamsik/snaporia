<script setup>
import { useForm } from '@inertiajs/vue3';
import { ImageOff, Search } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormSelect from '@/Components/FormSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    photos: {
        type: Object,
        required: true,
    },
    admins: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            admin_id: '',
            event_id: '',
            status: '',
            q: '',
        }),
    },
});

const form = useForm({
    admin_id: props.filters.admin_id ?? '',
    event_id: props.filters.event_id ?? '',
    status: props.filters.status ?? '',
    q: props.filters.q ?? '',
});

const submit = () => {
    form.get(route('super-admin.photos.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatBytes = (bytes) => {
    if (!bytes) {
        return '0 KB';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Monitoring Foto</h1>
                <Breadcrumbs :items="[{ label: 'Dashboard', href: route('dashboard') }, { label: 'Monitoring Foto' }]" />
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
                    placeholder="Cari nama file"
                />
                <FormSelect
                    v-model="form.admin_id"
                    :options="[{ label: 'Semua admin', value: '' }, ...admins.map((admin) => ({ label: admin.name, value: admin.id }))]"
                />
                <FormSelect
                    v-model="form.event_id"
                    :options="[{ label: 'Semua event', value: '' }, ...events.map((event) => ({ label: event.name, value: event.id }))]"
                />
                <FormSelect
                    v-model="form.status"
                    class="md:col-span-2"
                    :options="[
                        { label: 'Semua status', value: '' },
                        { label: 'Ready', value: 'ready' },
                        { label: 'Watermark gagal', value: 'watermark_failed' },
                    ]"
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

            <div v-if="photos.data.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="photo in photos.data" :key="photo.id" class="overflow-hidden rounded-lg border border-border bg-white">
                    <div class="aspect-[4/3] bg-surface">
                        <img
                            v-if="photo.preview_url"
                            :src="photo.preview_url"
                            :alt="photo.filename"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-ink-muted">
                            <ImageOff class="h-8 w-8" aria-hidden="true" />
                            <span class="text-sm font-semibold">Preview belum tersedia</span>
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink" :title="photo.filename">{{ photo.filename }}</p>
                                <p class="mt-1 text-sm text-ink-muted">{{ photo.event.name }}</p>
                            </div>
                            <StatusBadge :value="photo.status" />
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-ink">{{ photo.admin.name }}</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ photo.admin.email }}</p>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase text-ink-muted">Ukuran</p>
                                <p class="mt-1 text-ink">{{ formatBytes(photo.file_size) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-ink-muted">Harga</p>
                                <p class="mt-1 text-ink">{{ formatCurrency(photo.event.price_per_photo) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-ink-muted">Order</p>
                                <p class="mt-1 text-ink">{{ photo.order_items_count }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-border pt-3">
                            <StatusBadge :value="photo.event.is_published ? 'published' : 'draft'" />
                            <p class="text-sm text-ink-muted">{{ photo.mime_type }}</p>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-lg border border-border bg-white p-8">
                <EmptyState title="Belum ada foto" message="Foto dari semua fotografer akan muncul setelah proses upload berhasil." />
            </div>

            <Pagination :links="photos.links" />
        </div>
    </AuthenticatedLayout>
</template>
