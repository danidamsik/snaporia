<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Camera,
    CreditCard,
    Image,
    LayoutDashboard,
    Menu,
    Settings,
    Users,
    X,
} from 'lucide-vue-next';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const showingSidebar = ref(false);
const user = computed(() => page.props.auth.user);

const navByRole = {
    super_admin: [
        { label: 'Dashboard', href: '/super-admin/dashboard', icon: LayoutDashboard },
        { label: 'Manajemen Pengguna', href: '/super-admin/users', icon: Users },
        { label: 'Monitoring Event', href: '/super-admin/events', icon: Camera },
        { label: 'Monitoring Foto', href: '/super-admin/photos', icon: Image },
        { label: 'Transaksi', href: '/super-admin/transactions', icon: CreditCard },
        { label: 'Settings', href: '/super-admin/settings', icon: Settings },
    ],
    admin: [
        { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
        { label: 'Event Saya', href: '/admin/events', icon: Camera },
        { label: 'Foto Saya', href: '/admin/photos', icon: Image },
        { label: 'Transaksi', href: '/admin/transactions', icon: CreditCard },
        { label: 'Laporan Penjualan', href: '/admin/reports/sales', icon: BarChart3 },
    ],
};

const navigation = computed(() => navByRole[user.value?.role] ?? []);
const isActive = (href) => page.url === href || page.url.startsWith(`${href}/`);
</script>

<template>
    <div class="min-h-screen bg-surface">
        <Toast />

        <aside
            class="fixed inset-y-0 left-0 z-40 w-72 border-r border-border bg-white transition-transform lg:translate-x-0"
            :class="showingSidebar ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Navigasi dashboard"
        >
            <div class="flex h-16 items-center justify-between border-b border-border px-5">
                <Link :href="route('dashboard')">
                    <ApplicationLogo />
                </Link>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border text-ink lg:hidden"
                    aria-label="Tutup sidebar"
                    title="Tutup sidebar"
                    @click="showingSidebar = false"
                >
                    <X class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>

            <nav class="flex h-[calc(100vh-4rem)] flex-col justify-between p-4">
                <div class="space-y-1">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-semibold transition"
                        :class="isActive(item.href) ? 'bg-indigo-50 text-primary' : 'text-ink-muted hover:bg-surface hover:text-ink'"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span>{{ item.label }}</span>
                    </Link>
                </div>

                <div class="rounded-lg border border-border bg-surface p-3">
                    <p class="truncate text-sm font-semibold text-ink">{{ user.name }}</p>
                    <p class="mt-1 truncate text-xs text-ink-muted">{{ user.email }}</p>
                    <StatusBadge class="mt-3" :value="user.role" />
                </div>
            </nav>
        </aside>

        <div v-if="showingSidebar" class="fixed inset-0 z-30 bg-ink/30 lg:hidden" @click="showingSidebar = false" />

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-border bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-border text-ink lg:hidden"
                            aria-label="Buka sidebar"
                            title="Buka sidebar"
                            @click="showingSidebar = true"
                        >
                            <Menu class="h-5 w-5" aria-hidden="true" />
                        </button>
                        <div class="min-w-0">
                            <slot name="header">
                                <h1 class="truncate font-heading text-xl font-semibold text-ink">Dashboard</h1>
                            </slot>
                        </div>
                    </div>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-semibold text-ink hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                <span class="hidden max-w-40 truncate sm:inline">{{ user.name }}</span>
                                <StatusBadge :value="user.role" />
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profil</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Logout</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
