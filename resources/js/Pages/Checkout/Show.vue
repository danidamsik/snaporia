<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, Clock3, CreditCard, ExternalLink, Image, MapPin, RefreshCw, ShoppingCart } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    checkout: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    photos: props.checkout.photo_ids ?? [],
});
const paymentForm = useForm({});
const refreshForm = useForm({});

const isPreview = computed(() => props.checkout.mode === 'preview');
const isSingle = computed(() => props.checkout.type === 'single');
const typeLabel = computed(() => (isSingle.value ? 'Foto Satuan' : 'Paket Event'));

const submit = () => {
    form.post(props.checkout.store_url, {
        preserveScroll: true,
    });
};

const createPayment = () => {
    paymentForm.post(props.checkout.pay_url, {
        preserveScroll: true,
    });
};

const refreshStatus = () => {
    refreshForm.post(props.checkout.refresh_url, {
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
</script>

<template>
    <PublicLayout>
        <section class="border-b border-border bg-surface">
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <Link :href="checkout.event.url" class="text-sm font-semibold text-primary hover:text-primary-hover">
                    Kembali ke event
                </Link>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-normal text-primary">Checkout</p>
                        <h1 class="mt-1 font-heading text-3xl font-bold text-ink">Ringkasan Pembelian</h1>
                    </div>
                    <StatusBadge v-if="!isPreview" :value="checkout.status" />
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-5xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_340px] lg:px-8">
            <div class="space-y-5">
                <div class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-indigo-50 text-primary">
                            <ShoppingCart class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-ink-muted">Tipe pembelian</p>
                            <h2 class="mt-1 text-lg font-semibold text-ink">{{ typeLabel }}</h2>
                            <p v-if="checkout.order_code" class="mt-1 text-sm font-semibold text-primary">
                                {{ checkout.order_code }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 text-sm text-ink-muted sm:grid-cols-3">
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <Image class="h-4 w-4" aria-hidden="true" />
                            {{ checkout.photos_count }} foto
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <CalendarDays class="h-4 w-4" aria-hidden="true" />
                            {{ formatDate(checkout.event.date) }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <Clock3 class="h-4 w-4" aria-hidden="true" />
                            {{ formatDateTime(checkout.expires_at) }}
                        </span>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <p class="text-sm text-ink-muted">Event</p>
                    <h2 class="mt-1 text-xl font-semibold text-ink">{{ checkout.event.name }}</h2>
                    <p class="mt-3 flex items-center gap-2 text-sm text-ink-muted">
                        <MapPin class="h-4 w-4" aria-hidden="true" />
                        {{ checkout.event.location || 'Lokasi menyusul' }}
                    </p>
                </div>

                <div v-if="checkout.photos?.length" class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <h2 class="font-heading text-lg font-semibold text-ink">Foto dalam order</h2>
                    <div class="mt-4 divide-y divide-border">
                        <div v-for="photo in checkout.photos" :key="photo.id" class="flex items-center justify-between gap-3 py-3">
                            <p class="truncate text-sm font-semibold text-ink">{{ photo.filename }}</p>
                            <p class="shrink-0 text-sm text-ink-muted">{{ formatCurrency(photo.price) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rounded-lg border border-border bg-white p-5 shadow-sm lg:self-start">
                <h2 class="font-heading text-lg font-semibold text-ink">Total</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-muted">Subtotal</span>
                        <span class="font-semibold text-ink">{{ formatCurrency(checkout.total_amount) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-border pt-3">
                        <span class="text-ink-muted">Total bayar</span>
                        <span class="text-xl font-bold text-primary">{{ formatCurrency(checkout.total_amount) }}</span>
                    </div>
                </div>

                <form v-if="isPreview" class="mt-5" @submit.prevent="submit">
                    <InputError class="mb-3" :message="form.errors.photos || form.errors.event" />
                    <PrimaryButton type="submit" class="w-full" :disabled="form.processing">
                        <CreditCard class="h-4 w-4" aria-hidden="true" />
                        Buat Order
                    </PrimaryButton>
                </form>

                <div v-else class="mt-5 space-y-3">
                    <template v-if="checkout.status === 'pending'">
                        <a
                            v-if="checkout.payment?.payment_url"
                            :href="checkout.payment.payment_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <ExternalLink class="h-4 w-4" aria-hidden="true" />
                            Bayar via Midtrans
                        </a>
                        <PrimaryButton v-else type="button" class="w-full" :disabled="paymentForm.processing" @click="createPayment">
                            <CreditCard class="h-4 w-4" aria-hidden="true" />
                            Buat Pembayaran
                        </PrimaryButton>

                        <SecondaryButton
                            type="button"
                            class="w-full"
                            :disabled="refreshForm.processing || !checkout.payment"
                            @click="refreshStatus"
                        >
                            <RefreshCw class="h-4 w-4" aria-hidden="true" />
                            Cek Status
                        </SecondaryButton>
                    </template>

                    <div v-else-if="checkout.status === 'paid'" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        Pembayaran berhasil. Akses download tersedia pada menu pembelian.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            Pembayaran tidak aktif. Kamu bisa membuat order baru dari galeri event.
                        </div>
                        <Link
                            :href="checkout.event.url"
                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <ShoppingCart class="h-4 w-4" aria-hidden="true" />
                            Beli Ulang
                        </Link>
                    </div>

                    <div v-if="checkout.payment" class="rounded-md border border-border bg-surface p-3 text-xs text-ink-muted">
                        <p class="flex items-center justify-between gap-3">
                            <span>Status Midtrans</span>
                            <span class="font-semibold text-ink">{{ checkout.payment.status }}</span>
                        </p>
                        <p v-if="checkout.payment.payment_type" class="mt-2 flex items-center justify-between gap-3">
                            <span>Metode</span>
                            <span class="font-semibold text-ink">{{ checkout.payment.payment_type }}</span>
                        </p>
                    </div>
                </div>
            </aside>
        </section>
    </PublicLayout>
</template>
