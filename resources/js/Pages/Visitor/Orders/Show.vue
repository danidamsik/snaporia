<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, CreditCard, Download, FileText, Image, MapPin, ShoppingCart } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    items: {
        type: Object,
        required: true,
    },
});

const order = ref(props.order);
const isCreatingPayment = ref(false);
const isRefreshingStatus = ref(false);
let snapLoader = null;

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

const createPayment = async () => {
    if (!order.value.pay_url) {
        return;
    }

    isCreatingPayment.value = true;

    try {
        const snapPromise = loadSnap().then(
            (snap) => ({ snap }),
            (error) => ({ error })
        );
        const { data } = await axios.post(order.value.pay_url, {}, { headers: jsonHeaders });

        order.value = {
            ...order.value,
            status: data.order?.status ?? order.value.status,
            paid_at: data.order?.paid_at ?? order.value.paid_at,
            expires_at: data.order?.expires_at ?? order.value.expires_at,
            payment: data.payment,
        };

        const snapResult = await snapPromise;
        if (snapResult.error) {
            toast('error', 'Modal Midtrans belum bisa dibuka.', snapResult.error.message);
        } else {
            payWithSnap(snapResult.snap, data.payment?.snap_token);
        }
    } catch (error) {
        handlePaymentError(error);
    } finally {
        isCreatingPayment.value = false;
    }
};

const refreshStatus = async ({ silent = false } = {}) => {
    if (!order.value.refresh_url) {
        return;
    }

    isRefreshingStatus.value = true;

    try {
        const { data } = await axios.post(order.value.refresh_url, {}, { headers: jsonHeaders });

        order.value = {
            ...order.value,
            status: data.order?.status ?? order.value.status,
            paid_at: data.order?.paid_at ?? order.value.paid_at,
            expires_at: data.order?.expires_at ?? order.value.expires_at,
            payment: data.payment ?? order.value.payment,
        };

        if (order.value.status === 'paid') {
            window.location.reload();
            return;
        }

        if (!silent) {
            toast('success', data.message ?? 'Status pembayaran diperbarui.');
        }
    } catch (error) {
        if (!silent) {
            handlePaymentError(error);
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

        const snapConfig = order.value.midtrans ?? {};
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

const handlePaymentError = (error) => {
    const response = error.response;

    if (response?.data?.order) {
        order.value = {
            ...order.value,
            status: response.data.order.status ?? order.value.status,
            paid_at: response.data.order.paid_at ?? order.value.paid_at,
            expires_at: response.data.order.expires_at ?? order.value.expires_at,
        };
    }

    toast('error', response?.data?.message ?? 'Pembayaran belum bisa diproses.');
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

const typeLabel = (type) => (type === 'package' ? 'Paket Event' : 'Foto Satuan');

onMounted(() => {
    if (order.value.status === 'pending') {
        loadSnap().catch(() => {});
    }
});
</script>

<template>
    <PublicLayout>
        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link :href="route('visitor.orders.index')" class="text-sm font-semibold text-primary hover:text-primary-hover">
                    Riwayat Pembelian
                </Link>
                <StatusBadge :value="order.status" />
            </div>

            <section class="grid gap-5 lg:grid-cols-[1fr_340px]">
                <div class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm text-ink-muted">Kode order</p>
                            <h2 class="mt-1 font-heading text-2xl font-bold text-ink">{{ order.order_code }}</h2>
                            <Link :href="order.event.url" class="mt-2 block text-base font-semibold text-primary hover:text-primary-hover">
                                {{ order.event.name }}
                            </Link>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-sm text-ink-muted">Total</p>
                            <p class="mt-1 text-2xl font-bold text-primary">{{ formatCurrency(order.total_amount) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 text-sm text-ink-muted sm:grid-cols-2 xl:grid-cols-4">
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <FileText class="h-4 w-4" aria-hidden="true" />
                            {{ typeLabel(order.type) }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <Image class="h-4 w-4" aria-hidden="true" />
                            {{ order.items_count }} foto
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <CalendarDays class="h-4 w-4" aria-hidden="true" />
                            {{ formatDate(order.paid_at || order.created_at) }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2">
                            <MapPin class="h-4 w-4" aria-hidden="true" />
                            {{ order.event.location || 'Lokasi menyusul' }}
                        </span>
                    </div>
                </div>

                <aside v-if="order.status !== 'paid'" class="rounded-lg border border-border bg-white p-5 shadow-sm lg:self-start">
                    <h2 class="font-heading text-lg font-semibold text-ink">Akses Download</h2>
                    <p v-if="order.status === 'paid'" class="mt-2 text-sm text-ink-muted">
                        File original tersedia pada daftar foto di bawah.
                    </p>
                    <p v-else-if="order.status === 'pending'" class="mt-2 text-sm text-ink-muted">
                        Selesaikan pembayaran untuk membuka akses download.
                    </p>
                    <p v-else class="mt-2 text-sm text-ink-muted">
                        Download tersedia setelah pembayaran berhasil.
                    </p>
                    <button
                        v-if="order.status === 'pending' && order.payment?.snap_token"
                        type="button"
                        class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isCreatingPayment"
                        @click="openSnap(order.payment.snap_token)"
                    >
                        <CreditCard class="h-4 w-4" aria-hidden="true" />
                        Selesaikan Pembayaran
                    </button>
                    <button
                        v-else-if="order.status === 'pending'"
                        type="button"
                        class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isCreatingPayment"
                        @click="createPayment"
                    >
                        <CreditCard class="h-4 w-4" aria-hidden="true" />
                        Selesaikan Pembayaran
                    </button>
                    <Link
                        v-if="order.status !== 'paid' && order.status !== 'pending'"
                        :href="order.event.url"
                        class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <ShoppingCart class="h-4 w-4" aria-hidden="true" />
                        Beli Ulang
                    </Link>
                </aside>
            </section>

            <section class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-heading text-lg font-semibold text-ink">Daftar Foto</h2>
                        <p class="text-sm text-ink-muted">{{ items.total }} foto dalam order</p>
                    </div>
                </div>

                <div v-if="items.data.length" class="divide-y divide-border">
                    <article v-for="item in items.data" :key="item.id" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ item.filename }}</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ item.mime_type }} · {{ formatFileSize(item.file_size) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center justify-between gap-3 sm:min-w-56">
                            <p class="text-sm font-semibold text-ink">{{ formatCurrency(item.price) }}</p>
                            <a
                                v-if="item.download_url"
                                :href="item.download_url"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                <Download class="h-4 w-4" aria-hidden="true" />
                                Download
                            </a>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink-muted disabled:cursor-not-allowed"
                            >
                                <Download class="h-4 w-4" aria-hidden="true" />
                                Download
                            </button>
                        </div>
                    </article>
                </div>

                <EmptyState v-else title="Foto tidak ditemukan" message="Order ini belum memiliki item foto.">
                    <template #icon>
                        <Image class="h-6 w-6" aria-hidden="true" />
                    </template>
                </EmptyState>

                <Pagination class="mt-5" :links="items.links" />
            </section>
        </section>
    </PublicLayout>
</template>
