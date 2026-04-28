<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ImageOff, Search, Trash2, Upload } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    photos: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        default: () => [],
    },
    selectedEvent: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({ event_id: '', q: '' }),
    },
});

const form = useForm({
    event_id: props.filters.event_id ?? '',
    q: props.filters.q ?? '',
});

const submit = () => {
    form.get(route('admin.photos.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(value))
        : 'Tanggal kosong';

const formatBytes = (bytes) => {
    if (!bytes) {
        return '0 KB';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
};

const deletePhoto = (photo) => {
    if (!confirm(`Hapus foto ${photo.filename}?`)) {
        return;
    }

    router.delete(route('admin.photos.destroy', photo.id), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Foto Saya</h1>
                <p class="mt-1 text-sm text-ink-muted">
                    {{ selectedEvent ? selectedEvent.name : 'Kelola foto dari semua event milik Anda.' }}
                </p>
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 lg:flex-row lg:items-end lg:justify-between">
                <form class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-[220px_1fr_auto]" @submit.prevent="submit">
                    <select
                        v-model="form.event_id"
                        class="min-h-10 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                        aria-label="Filter event"
                    >
                        <option value="">Semua event</option>
                        <option v-for="event in events" :key="event.id" :value="event.id">
                            {{ event.name }}
                        </option>
                    </select>

                    <TextInput v-model="form.q" type="search" maxlength="100" placeholder="Cari nama file" />

                    <SecondaryButton type="submit" :disabled="form.processing">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Filter
                    </SecondaryButton>
                </form>

                <Link
                    :href="route('admin.photos.upload')"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <Upload class="h-4 w-4" aria-hidden="true" />
                    Upload Foto
                </Link>
            </div>

            <div v-if="photos.data.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="photo in photos.data" :key="photo.id" class="overflow-hidden rounded-lg border border-border bg-white">
                    <div class="aspect-[4/3] bg-surface">
                        <img
                            v-if="photo.status === 'ready'"
                            :src="photo.watermarked_url"
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
                                <p class="mt-1 text-sm text-ink-muted">{{ photo.event_name }}</p>
                            </div>
                            <StatusBadge :value="photo.status" />
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase text-ink-muted">Ukuran</p>
                                <p class="mt-1 text-ink">{{ formatBytes(photo.file_size) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-ink-muted">Urutan</p>
                                <p class="mt-1 text-ink">{{ photo.sort_order }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-ink-muted">Order</p>
                                <p class="mt-1 text-ink">{{ photo.order_items_count }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-border pt-3">
                            <p class="text-sm text-ink-muted">{{ photo.mime_type }}</p>
                            <IconButton
                                v-if="photo.can_delete"
                                label="Hapus foto"
                                variant="danger"
                                :disabled="photo.order_items_count > 0"
                                @click="deletePhoto(photo)"
                            >
                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                            </IconButton>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-lg border border-border bg-white p-8">
                <EmptyState title="Belum ada foto" message="Upload foto event untuk mulai menampilkan galeri watermark." />
            </div>

            <Pagination :links="photos.links" />
        </div>
    </AuthenticatedLayout>
</template>
