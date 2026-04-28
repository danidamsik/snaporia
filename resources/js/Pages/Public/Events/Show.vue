<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { CalendarDays, Download, Image, MapPin, Search, ShoppingCart } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
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
    form.get(props.event.url, {
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
              month: 'long',
              year: 'numeric',
          }).format(new Date(value))
        : 'Tanggal menyusul';
</script>

<template>
    <PublicLayout>
        <section class="border-b border-border bg-surface">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Link :href="route('events.index')" class="text-sm font-semibold text-primary hover:text-primary-hover">
                    Semua Event
                </Link>
                <div class="mt-4 grid gap-5 lg:grid-cols-[1fr_340px]">
                    <div>
                        <h1 class="font-heading text-3xl font-bold text-ink sm:text-4xl">{{ event.name }}</h1>
                        <p v-if="event.description" class="mt-3 max-w-3xl text-sm leading-6 text-ink-muted sm:text-base">
                            {{ event.description }}
                        </p>
                        <div class="mt-5 flex flex-wrap gap-3 text-sm text-ink-muted">
                            <span class="inline-flex items-center gap-2 rounded-md border border-border bg-white px-3 py-2">
                                <CalendarDays class="h-4 w-4" aria-hidden="true" />
                                {{ formatDate(event.date) }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-md border border-border bg-white px-3 py-2">
                                <MapPin class="h-4 w-4" aria-hidden="true" />
                                {{ event.location || 'Lokasi menyusul' }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-md border border-border bg-white px-3 py-2">
                                <Image class="h-4 w-4" aria-hidden="true" />
                                {{ event.photos_count }} foto
                            </span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-white p-4 shadow-sm">
                        <div class="grid gap-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-ink-muted">Foto satuan</span>
                                <span class="font-semibold text-ink">{{ formatCurrency(event.price_per_photo) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-ink-muted">Paket event</span>
                                <span class="font-semibold text-primary">{{ formatCurrency(event.price_package) }}</span>
                            </div>
                            <PrimaryButton type="button" disabled>
                                <ShoppingCart class="h-4 w-4" aria-hidden="true" />
                                Beli Paket
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="font-heading text-xl font-semibold text-ink">Galeri Event</h2>
                    <p class="text-sm text-ink-muted">{{ photos.total }} foto watermark tersedia</p>
                </div>
                <form class="flex w-full gap-2 lg:max-w-md" @submit.prevent="submit">
                    <TextInput v-model="form.q" type="search" maxlength="100" class="flex-1" placeholder="Cari nama file foto" />
                    <SecondaryButton type="submit" :disabled="form.processing">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Cari
                    </SecondaryButton>
                </form>
            </div>

            <div v-if="photos.data.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article v-for="photo in photos.data" :key="photo.id" class="overflow-hidden rounded-lg border border-border bg-white shadow-sm">
                    <div class="aspect-[4/3] bg-surface">
                        <img
                            :src="photo.watermarked_url"
                            :alt="photo.filename"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                    <div class="p-3">
                        <p class="truncate text-sm font-semibold text-ink">{{ photo.filename }}</p>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-sm text-ink-muted">{{ formatCurrency(event.price_per_photo) }}</span>
                            <PrimaryButton type="button" disabled>
                                <Download class="h-4 w-4" aria-hidden="true" />
                                Beli
                            </PrimaryButton>
                        </div>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                title="Foto tidak ditemukan"
                message="Coba gunakan nama file yang berbeda."
            >
                <template #icon>
                    <Image class="h-6 w-6" aria-hidden="true" />
                </template>
            </EmptyState>

            <Pagination class="mt-6" :links="photos.links" />
        </section>
    </PublicLayout>
</template>
