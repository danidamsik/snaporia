<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const isVisitor = computed(() => page.props.auth.user?.role === 'visitor');
const layout = computed(() => (isVisitor.value ? PublicLayout : AuthenticatedLayout));
</script>

<template>
    <Head title="Profile" />

    <component :is="layout">
        <template v-if="!isVisitor" #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Profil</h1>
                <Breadcrumbs :items="[{ label: 'Dashboard', href: route('dashboard') }, { label: 'Profil' }]" />
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="isVisitor" class="px-4 sm:px-0">
                    <h1 class="font-heading text-2xl font-semibold text-ink">Profil</h1>
                    <p class="mt-1 text-sm text-ink-muted">Kelola informasi akun dan password.</p>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </component>
</template>
