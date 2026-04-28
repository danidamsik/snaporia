<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CreditCard,
    Download,
    FileText,
    Image,
    Settings,
    Upload,
    Users,
} from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    dashboardRole: {
        type: String,
        default: 'visitor',
    },
    stats: {
        type: Array,
        default: () => [],
    },
    recentTransactions: {
        type: Array,
        default: () => [],
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
    recentEvents: {
        type: Array,
        default: () => [],
    },
    quickLinks: {
        type: Array,
        default: () => [],
    },
});

const iconMap = {
    banknote: Banknote,
    calendar: CalendarDays,
    check: CheckCircle2,
    clock: Clock3,
    'credit-card': CreditCard,
    download: Download,
    file: FileText,
    image: Image,
    settings: Settings,
    upload: Upload,
    users: Users,
};

const iconClasses = {
    banknote: 'bg-green-50 text-green-700',
    calendar: 'bg-indigo-50 text-primary',
    check: 'bg-green-50 text-green-700',
    clock: 'bg-amber-50 text-amber-700',
    'credit-card': 'bg-cyan-50 text-cyan-700',
    download: 'bg-indigo-50 text-primary',
    file: 'bg-amber-50 text-amber-700',
    image: 'bg-cyan-50 text-cyan-700',
    settings: 'bg-gray-100 text-gray-700',
    upload: 'bg-indigo-50 text-primary',
    users: 'bg-indigo-50 text-primary',
};

const roleTitle = {
    super_admin: 'Dashboard Super Admin',
    admin: 'Dashboard Admin',
    visitor: 'Dashboard Visitor',
};

const roleDescription = {
    super_admin: 'Ringkasan user, event, foto, order, dan transaksi seluruh sistem.',
    admin: 'Ringkasan event, foto, transaksi, dan total penjualan milik Anda.',
    visitor: 'Ringkasan order terbaru, status pembayaran, dan akses download.',
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);

const formatNumber = (value) =>
    new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(value ?? 0);

const formatStat = (stat) => (stat.format === 'currency' ? formatCurrency(stat.value) : formatNumber(stat.value));

const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(value))
        : '-';

const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : '-';

const typeLabel = (type) => (type === 'package' ? 'Paket Event' : 'Foto Satuan');
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">{{ roleTitle[dashboardRole] ?? 'Dashboard' }}</h1>
                <p class="mt-1 text-sm text-ink-muted">{{ roleDescription[dashboardRole] ?? 'Ringkasan aktivitas Snaporia' }}</p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="flex flex-col gap-4 rounded-lg border border-border bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-primary">Snaporia</p>
                    <h2 class="mt-1 font-heading text-2xl font-bold text-ink">Find Your Moments.</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        v-for="link in quickLinks"
                        :key="link.href"
                        :href="link.href"
                        class="inline-flex min-h-10 items-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-semibold text-ink transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <component :is="iconMap[link.icon] ?? FileText" class="h-4 w-4" aria-hidden="true" />
                        {{ link.label }}
                    </Link>
                    <StatusBadge :value="dashboardRole" />
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="stat in stats" :key="stat.label" class="rounded-lg border border-border bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-ink-muted">{{ stat.label }}</p>
                            <p class="mt-2 text-2xl font-bold text-ink">{{ formatStat(stat) }}</p>
                        </div>
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-md" :class="iconClasses[stat.icon] ?? 'bg-surface text-ink'">
                            <component :is="iconMap[stat.icon] ?? FileText" class="h-5 w-5" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="recentTransactions.length" class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-heading text-lg font-semibold text-ink">Transaksi Terbaru</h2>
                    <Link
                        :href="dashboardRole === 'super_admin' ? route('super-admin.transactions.index') : route('admin.transactions.index')"
                        class="text-sm font-semibold text-primary hover:text-primary-hover"
                    >
                        Lihat semua
                    </Link>
                </div>
                <div class="divide-y divide-border">
                    <article v-for="transaction in recentTransactions" :key="transaction.id" class="flex flex-col gap-3 py-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <Link :href="transaction.url" class="font-semibold text-primary hover:text-primary-hover">
                                {{ transaction.order.order_code }}
                            </Link>
                            <p class="mt-1 truncate text-sm text-ink-muted">
                                {{ transaction.event.name }} - {{ transaction.user.name }}
                                <span v-if="transaction.admin"> - {{ transaction.admin.name }}</span>
                            </p>
                            <p class="mt-1 text-xs text-ink-muted">{{ formatDateTime(transaction.created_at) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center justify-between gap-3 lg:min-w-56">
                            <StatusBadge :value="transaction.order_status" />
                            <p class="text-sm font-semibold text-ink">{{ formatCurrency(transaction.gross_amount) }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="recentEvents.length" class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-heading text-lg font-semibold text-ink">Event Terbaru</h2>
                    <Link
                        v-if="dashboardRole === 'admin'"
                        :href="route('admin.events.index')"
                        class="text-sm font-semibold text-primary hover:text-primary-hover"
                    >
                        Lihat semua
                    </Link>
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="event in recentEvents" :key="event.id" class="rounded-md border border-border bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink">{{ event.name }}</p>
                                <p class="mt-1 text-sm text-ink-muted">{{ formatDate(event.date) }} - {{ event.location || 'Lokasi kosong' }}</p>
                                <p v-if="event.admin" class="mt-1 text-xs text-ink-muted">{{ event.admin.name }}</p>
                            </div>
                            <StatusBadge :value="event.is_published ? 'published' : 'draft'" />
                        </div>
                        <p class="mt-3 text-sm text-ink-muted">{{ event.photos_count }} foto</p>
                    </article>
                </div>
            </section>

            <section v-if="recentOrders.length" class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-heading text-lg font-semibold text-ink">Order Terbaru</h2>
                    <Link :href="route('visitor.orders.index')" class="text-sm font-semibold text-primary hover:text-primary-hover">
                        Lihat semua
                    </Link>
                </div>
                <div class="divide-y divide-border">
                    <article v-for="order in recentOrders" :key="order.id" class="flex flex-col gap-3 py-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <Link :href="order.url" class="font-semibold text-primary hover:text-primary-hover">
                                {{ order.order_code }}
                            </Link>
                            <p class="mt-1 truncate text-sm text-ink-muted">{{ order.event.name }} - {{ typeLabel(order.type) }}</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ formatDateTime(order.paid_at || order.created_at) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center justify-between gap-3 lg:min-w-56">
                            <StatusBadge :value="order.status" />
                            <p class="text-sm font-semibold text-ink">{{ formatCurrency(order.total_amount) }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <EmptyState
                v-if="!recentTransactions.length && !recentEvents.length && !recentOrders.length"
                title="Data dashboard belum tersedia"
                message="Ringkasan akan terisi saat aktivitas operasional mulai berjalan."
            >
                <template #icon>
                    <Users class="h-6 w-6" aria-hidden="true" />
                </template>
            </EmptyState>
        </div>
    </AuthenticatedLayout>
</template>
