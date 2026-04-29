<script setup>
import { computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Camera, Image, MapPin, Search } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    events: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ q: '', date: '', location: '' }),
    },
});

const form = useForm({
    q: props.filters.q ?? '',
    date: props.filters.date ?? '',
    location: props.filters.location ?? '',
});
const page = usePage();
const appName = computed(() => page.props.app?.name ?? 'Snaporia');
const appTagline = computed(() => page.props.app?.tagline ?? 'Find Your Moments.');

const submit = () => {
    form.get(route('events.index'), {
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
            <div class="mx-auto grid max-w-7xl gap-5 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ appName }}</p>
                    <h1 class="mt-2 font-heading text-3xl font-bold text-ink sm:text-4xl">{{ appTagline }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-ink-muted sm:text-base">
                        Temukan event, lihat preview watermark, lalu beli foto original dari fotografer resmi.
                    </p>
                </div>
                <form class="rounded-lg border border-border bg-white p-4 shadow-sm" @submit.prevent="submit">
                    <div class="grid gap-3">
                        <TextInput v-model="form.q" type="search" maxlength="100" placeholder="Nama event atau file foto" />
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <TextInput v-model="form.date" type="date" aria-label="Tanggal event" />
                            <TextInput v-model="form.location" maxlength="100" placeholder="Lokasi event" />
                        </div>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            <Search class="h-4 w-4" aria-hidden="true" />
                            Cari Foto
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-heading text-xl font-semibold text-ink">Event Publik</h2>
                    <p class="text-sm text-ink-muted">{{ events.total }} event ditemukan</p>
                </div>
            </div>

            <div v-if="events.data.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="event in events.data"
                    :key="event.id"
                    :href="event.url"
                    class="group overflow-hidden rounded-lg border border-border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="aspect-[4/3] bg-surface">
                        <img
                            v-if="event.cover_url"
                            :src="event.cover_url"
                            :alt="event.name"
                            class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                            loading="lazy"
                        />
                        <div v-else class="grid h-full place-items-center text-ink-muted">
                            <Camera class="h-10 w-10" aria-hidden="true" />
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="line-clamp-2 text-base font-semibold text-ink">{{ event.name }}</h3>
                        <div class="mt-3 space-y-2 text-sm text-ink-muted">
                            <p class="flex items-center gap-2">
                                <CalendarDays class="h-4 w-4" aria-hidden="true" />
                                {{ formatDate(event.date) }}
                            </p>
                            <p class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" aria-hidden="true" />
                                {{ event.location || 'Lokasi menyusul' }}
                            </p>
                            <p class="flex items-center gap-2">
                                <Image class="h-4 w-4" aria-hidden="true" />
                                {{ event.photos_count }} foto
                            </p>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-4">
                            <span class="text-sm text-ink-muted">Mulai</span>
                            <span class="text-sm font-semibold text-primary">{{ formatCurrency(event.price_per_photo) }}</span>
                        </div>
                    </div>
                </Link>
            </div>

            <EmptyState
                v-else
                title="Event tidak ditemukan"
                message="Coba gunakan nama event, tanggal, lokasi, atau nama file foto yang berbeda."
            >
                <template #icon>
                    <CalendarDays class="h-6 w-6" aria-hidden="true" />
                </template>
            </EmptyState>

            <Pagination class="mt-6" :links="events.links" />
        </section>
    </PublicLayout>
</template>
