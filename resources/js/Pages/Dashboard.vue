<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Camera, CreditCard, Download, Image, Users } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    dashboardRole: {
        type: String,
        default: 'visitor',
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Dashboard</h1>
                <p class="mt-1 text-sm text-ink-muted">Ringkasan aktivitas Snaporia</p>
            </div>
        </template>

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-white p-5">
                <div>
                    <p class="text-sm font-semibold text-primary">Snaporia</p>
                    <h2 class="mt-1 font-heading text-2xl font-bold text-ink">Find Your Moments.</h2>
                </div>
                <StatusBadge :value="dashboardRole" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="item in [
                        { label: 'Event', value: 0, icon: Camera, color: 'text-primary bg-indigo-50' },
                        { label: 'Foto', value: 0, icon: Image, color: 'text-secondary bg-cyan-50' },
                        { label: 'Order', value: 0, icon: Download, color: 'text-accent bg-amber-50' },
                        { label: 'Transaksi', value: 0, icon: CreditCard, color: 'text-green-700 bg-green-50' },
                    ]"
                    :key="item.label"
                    class="rounded-lg border border-border bg-white p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-ink-muted">{{ item.label }}</p>
                            <p class="mt-2 text-2xl font-bold text-ink">{{ item.value }}</p>
                        </div>
                        <div class="grid h-11 w-11 place-items-center rounded-md" :class="item.color">
                            <component :is="item.icon" class="h-5 w-5" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </div>

            <EmptyState title="Data dashboard belum tersedia" message="Ringkasan akan terisi setelah modul operasional dibuat pada phase berikutnya.">
                <template #icon>
                    <Users class="h-6 w-6" aria-hidden="true" />
                </template>
            </EmptyState>
        </div>
    </AuthenticatedLayout>
</template>
