<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Toast from '@/Components/Toast.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-surface px-4 pt-8 sm:justify-center sm:pt-0">
        <Toast />
        <div class="mb-8">
            <Link href="/">
                <ApplicationLogo />
            </Link>
        </div>

        <div class="w-full overflow-hidden rounded-lg border border-border bg-white px-6 py-5 shadow-sm sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
