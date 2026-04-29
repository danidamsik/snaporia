<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Clock3, CreditCard, ExternalLink, Image, MapPin, RefreshCw, ShoppingCart } from 'lucide-vue-next';
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

const checkout = ref(props.checkout);
const formErrors = ref({});
const isSubmittingOrder = ref(false);
const isCreatingPayment = ref(false);
const isRefreshingStatus = ref(false);
let snapLoader = null;

const isPreview = computed(() => checkout.value.mode === 'preview');
const isSingle = computed(() => checkout.value.type === 'single');
const typeLabel = computed(() => (isSingle.value ? 'Foto Satuan' : 'Paket Event'));

const toast = (type, title, message = '') => {
    window.dispatchEvent(
        new CustomEvent('snaporia:toast', {
            detail: { type, title, message },
        })
    );
};

const jsonHeaders = {
    Accept: 'application/json',
};

const submit = async () => {
    formErrors.value = {};
    isSubmittingOrder.value = true;

    try {
        const snapPromise = loadSnap().then(
            (snap) => ({ snap }),
            (error) => ({ error })
        );
        const { data } = await axios.post(
            checkout.value.store_url,
            {
                photos: checkout.value.photo_ids ?? [],
            },
            { headers: jsonHeaders }
        );

        checkout.value = data.checkout;
        toast('success', data.message ?? 'Order berhasil dibuat.');
        const snapResult = await snapPromise;
        if (snapResult.error) {
            toast('error', 'Modal Midtrans belum bisa dibuka.', snapResult.error.message);
        } else {
            payWithSnap(snapResult.snap, data.payment?.snap_token ?? data.checkout?.payment?.snap_token);
        }
    } catch (error) {
        handleCheckoutError(error);
    } finally {
        isSubmittingOrder.value = false;
    }
};

const createPayment = async () => {
    if (!checkout.value.pay_url) {
        return;
    }

    formErrors.value = {};
    isCreatingPayment.value = true;

    try {
        const snapPromise = loadSnap().then(
            (snap) => ({ snap }),
            (error) => ({ error })
        );
        const { data } = await axios.post(checkout.value.pay_url, {}, { headers: jsonHeaders });

        checkout.value = {
            ...checkout.value,
            status: data.order?.status ?? checkout.value.status,
            paid_at: data.order?.paid_at ?? checkout.value.paid_at,
            expires_at: data.order?.expires_at ?? checkout.value.expires_at,
            payment: data.payment,
        };

        toast('success', data.message ?? 'Pembayaran siap dibuka.');
        const snapResult = await snapPromise;
        if (snapResult.error) {
            toast('error', 'Modal Midtrans belum bisa dibuka.', snapResult.error.message);
        } else {
            payWithSnap(snapResult.snap, data.payment?.snap_token);
        }
    } catch (error) {
        handleCheckoutError(error);
    } finally {
        isCreatingPayment.value = false;
    }
};

const refreshStatus = async ({ silent = false } = {}) => {
    if (!checkout.value.refresh_url) {
        return;
    }

    isRefreshingStatus.value = true;

    try {
        const { data } = await axios.post(checkout.value.refresh_url, {}, { headers: jsonHeaders });

        checkout.value = {
            ...checkout.value,
            status: data.order?.status ?? checkout.value.status,
            paid_at: data.order?.paid_at ?? checkout.value.paid_at,
            expires_at: data.order?.expires_at ?? checkout.value.expires_at,
            payment: data.payment ?? checkout.value.payment,
        };

        if (!silent) {
            toast('success', data.message ?? 'Status pembayaran diperbarui.');
        }
    } catch (error) {
        if (!silent) {
            handleCheckoutError(error);
        }
    } finally {
        isRefreshingStatus.value = false;
    }
};

const openSnap = async (snapToken) => {
    if (!snapToken) {
        toast('warning', 'Token pembayaran belum tersedia.');
        return;
    }

    try {
        const snap = await loadSnap();
        payWithSnap(snap, snapToken);
    } catch (error) {
        toast('error', 'Modal Midtrans belum bisa dibuka.', error.message);
    }
};

const payWithSnap = (snap, snapToken) => {
    if (!snapToken) {
        toast('warning', 'Token pembayaran belum tersedia.');
        return;
    }

    if (!snap?.pay) {
        toast('error', 'Modal Midtrans belum siap.');
        return;
    }

    snap.pay(snapToken, {
        onSuccess: async () => {
            toast('success', 'Pembayaran berhasil diproses.');
            await refreshStatus({ silent: true });
        },
        onPending: async () => {
            toast('info', 'Pembayaran masih menunggu konfirmasi.');
            await refreshStatus({ silent: true });
        },
        onError: () => {
            toast('error', 'Pembayaran gagal diproses.');
        },
        onClose: () => {
            toast('info', 'Modal pembayaran ditutup.');
        },
    });
};

const loadSnap = () => {
    if (snapLoader) {
        return snapLoader;
    }

    snapLoader = new Promise((resolve, reject) => {
        if (window.snap?.pay) {
            resolve(window.snap);
            return;
        }

        const snapConfig = checkout.value.midtrans ?? {};
        if (!snapConfig.client_key) {
            reject(new Error('Client key Midtrans belum dikonfigurasi.'));
            return;
        }

        const existingScript = document.getElementById('midtrans-snap-js');
        if (existingScript) {
            if (existingScript.dataset.loaded === 'true' && window.snap?.pay) {
                resolve(window.snap);
                return;
            }

            existingScript.addEventListener('load', () => resolve(window.snap), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Gagal memuat Snap JS.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = 'midtrans-snap-js';
        script.src = snapConfig.snap_js_url;
        script.dataset.clientKey = snapConfig.client_key;
        script.onload = () => {
            script.dataset.loaded = 'true';
            resolve(window.snap);
        };
        script.onerror = () => reject(new Error('Gagal memuat Snap JS.'));
        document.body.appendChild(script);
    });

    snapLoader.catch(() => {
        snapLoader = null;
    });

    return snapLoader;
};

const handleCheckoutError = (error) => {
    const response = error.response;

    if (response?.status === 422 && response.data?.errors) {
        formErrors.value = response.data.errors;
        toast('warning', 'Periksa kembali data checkout.');
        return;
    }

    if (response?.data?.checkout) {
        checkout.value = response.data.checkout;
    }

    toast('error', response?.data?.message ?? 'Checkout belum bisa diproses.');
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

onMounted(() => {
    loadSnap().catch(() => {});
});
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
                    <InputError class="mb-3" :message="formErrors.photos?.[0] || formErrors.event?.[0]" />
                    <PrimaryButton type="submit" class="w-full" :disabled="isSubmittingOrder">
                        <CreditCard class="h-4 w-4" aria-hidden="true" />
                        Buat Order & Bayar
                    </PrimaryButton>
                </form>

                <div v-else class="mt-5 space-y-3">
                    <template v-if="checkout.status === 'pending'">
                        <PrimaryButton
                            v-if="checkout.payment?.snap_token"
                            type="button"
                            class="w-full"
                            :disabled="isCreatingPayment"
                            @click="openSnap(checkout.payment.snap_token)"
                        >
                            <CreditCard class="h-4 w-4" aria-hidden="true" />
                            Bayar via Midtrans
                        </PrimaryButton>
                        <PrimaryButton v-else type="button" class="w-full" :disabled="isCreatingPayment" @click="createPayment">
                            <CreditCard class="h-4 w-4" aria-hidden="true" />
                            Buat Pembayaran
                        </PrimaryButton>
                        <a
                            v-if="checkout.payment?.payment_url"
                            :href="checkout.payment.payment_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <ExternalLink class="h-4 w-4" aria-hidden="true" />
                            Buka Halaman Midtrans
                        </a>

                        <SecondaryButton
                            type="button"
                            class="w-full"
                            :disabled="isRefreshingStatus || !checkout.payment"
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
