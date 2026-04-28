<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { CalendarDays, Image, MapPin, Search } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    photos: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ q: '' }),
    },
});

const form = useForm({
    q: props.filters.q ?? '',
});

const submit = () => {
    form.get(route('gallery.index'), {
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
        : 'Tanggal menyusul';
</script>

<template>
    <PublicLayout>
        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="font-heading text-2xl font-bold text-ink sm:text-3xl">Galeri Foto</h1>
                    <p class="mt-2 text-sm text-ink-muted">{{ photos.total }} foto watermark dari event publik</p>
                </div>
                <form class="flex w-full gap-2 lg:max-w-md" @submit.prevent="submit">
                    <TextInput v-model="form.q" type="search" maxlength="100" class="flex-1" placeholder="Cari event, lokasi, tanggal, atau file" />
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Cari
                    </PrimaryButton>
                </form>
            </div>

            <div v-if="photos.data.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article v-for="photo in photos.data" :key="photo.id" class="overflow-hidden rounded-lg border border-border bg-white shadow-sm">
                    <Link :href="photo.event.url" class="block aspect-[4/3] bg-surface">
                        <img
                            :src="photo.watermarked_url"
                            :alt="photo.filename"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </Link>
                    <div class="p-3">
                        <p class="truncate text-sm font-semibold text-ink">{{ photo.filename }}</p>
                        <Link :href="photo.event.url" class="mt-1 block truncate text-sm font-semibold text-primary hover:text-primary-hover">
                            {{ photo.event.name }}
                        </Link>
                        <div class="mt-3 space-y-1 text-xs text-ink-muted">
                            <p class="flex items-center gap-2">
                                <CalendarDays class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ formatDate(photo.event.date) }}
                            </p>
                            <p class="flex items-center gap-2">
                                <MapPin class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ photo.event.location || 'Lokasi menyusul' }}
                            </p>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-ink">{{ formatCurrency(photo.event.price_per_photo) }}</p>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                title="Foto tidak ditemukan"
                message="Coba gunakan nama event, tanggal, lokasi, atau nama file yang berbeda."
            >
                <template #icon>
                    <Image class="h-6 w-6" aria-hidden="true" />
                </template>
            </EmptyState>

            <Pagination class="mt-6" :links="photos.links" />
        </section>
    </PublicLayout>
</template>
