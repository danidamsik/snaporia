<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LogOut, Menu, ShoppingBag, UserRound, X } from 'lucide-vue-next';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const showingMenu = ref(false);
const user = computed(() => page.props.auth.user);

const publicLinks = [
    { label: 'Event', href: '/events' },
    { label: 'Galeri', href: '/gallery' },
];

const visitorLinks = [
    { label: 'Event', href: '/events' },
    { label: 'Galeri', href: '/gallery' },
    { label: 'Riwayat Pembelian', href: '/visitor/orders' },
    { label: 'Profil', href: '/profile' },
];

const mainLinks = computed(() => (user.value?.role === 'visitor' ? visitorLinks : publicLinks));
const isActive = (href) => page.url === href || page.url.startsWith(`${href}/`);
</script>

<template>
    <div class="min-h-screen bg-white">
        <Toast />
        <header class="sticky top-0 z-30 border-b border-border bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="shrink-0">
                    <ApplicationLogo />
                </Link>

                <nav class="hidden items-center gap-1 md:flex" aria-label="Navigasi publik">
                    <Link
                        v-for="item in mainLinks"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 text-sm font-semibold transition"
                        :class="isActive(item.href) ? 'bg-indigo-50 text-primary' : 'text-ink-muted hover:bg-surface hover:text-ink'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    <Link
                        v-if="user && user.role !== 'visitor'"
                        :href="route('dashboard')"
                        class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-semibold text-white hover:bg-primary-hover"
                    >
                        <UserRound class="h-4 w-4" aria-hidden="true" />
                        Dashboard
                    </Link>
                    <Link
                        v-else-if="user"
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-semibold text-white hover:bg-primary-hover"
                    >
                        <LogOut class="h-4 w-4" aria-hidden="true" />
                        Logout
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-md px-3 py-2 text-sm font-semibold text-ink-muted hover:text-ink"
                        >
                            Login
                        </Link>
                        <Link :href="route('register')" class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-semibold text-white hover:bg-primary-hover">
                            <ShoppingBag class="h-4 w-4" aria-hidden="true" />
                            Register
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-border text-ink md:hidden"
                    :aria-label="showingMenu ? 'Tutup menu' : 'Buka menu'"
                    :title="showingMenu ? 'Tutup menu' : 'Buka menu'"
                    @click="showingMenu = !showingMenu"
                >
                    <X v-if="showingMenu" class="h-5 w-5" aria-hidden="true" />
                    <Menu v-else class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>

            <div v-if="showingMenu" class="border-t border-border bg-white px-4 py-3 md:hidden">
                <nav class="space-y-1" aria-label="Navigasi publik mobile">
                    <Link
                        v-for="item in mainLinks"
                        :key="item.href"
                        :href="item.href"
                        class="block rounded-md px-3 py-2 text-sm font-semibold"
                        :class="isActive(item.href) ? 'bg-indigo-50 text-primary' : 'text-ink-muted hover:bg-surface hover:text-ink'"
                    >
                        {{ item.label }}
                    </Link>
                    <Link
                        v-if="user && user.role !== 'visitor'"
                        :href="route('dashboard')"
                        class="block rounded-md px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-surface hover:text-ink"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-if="!user"
                        :href="route('login')"
                        class="block rounded-md px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-surface hover:text-ink"
                    >
                        Login
                    </Link>
                    <Link
                        v-if="user && user.role === 'visitor'"
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="block w-full rounded-md px-3 py-2 text-left text-sm font-semibold text-primary hover:bg-indigo-50"
                    >
                        Logout
                    </Link>
                    <Link v-if="!user" :href="route('register')" class="block rounded-md px-3 py-2 text-sm font-semibold text-primary hover:bg-indigo-50">
                        Register
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
