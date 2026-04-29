<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { Edit, Plus, Search, Trash2, UserX } from 'lucide-vue-next';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import DataTable from '@/Components/DataTable.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormSelect from '@/Components/FormSelect.vue';
import IconButton from '@/Components/IconButton.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ role: '', status: '', q: '' }),
    },
});

const columns = [
    { key: 'user', label: 'User' },
    { key: 'role', label: 'Role' },
    { key: 'status', label: 'Status' },
    { key: 'operational', label: 'Data Operasional' },
    { key: 'actions', label: 'Aksi' },
];

const form = useForm({
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
    q: props.filters.q ?? '',
});
const userToDelete = ref(null);
const deleteProcessing = ref(false);

const submit = () => {
    form.get(route('super-admin.users.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const deactivateUser = (user) => {
    if (!confirm(`Nonaktifkan akun ${user.name}?`)) {
        return;
    }

    router.patch(route('super-admin.users.deactivate', user.id), {}, { preserveScroll: true });
};

const confirmDeleteUser = (user) => {
    userToDelete.value = user;
};

const closeDeleteModal = () => {
    if (!deleteProcessing.value) {
        userToDelete.value = null;
    }
};

const deleteUser = () => {
    if (!userToDelete.value) {
        return;
    }

    deleteProcessing.value = true;

    router.delete(route('super-admin.users.destroy', userToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            userToDelete.value = null;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Manajemen Pengguna</h1>
                <Breadcrumbs :items="[{ label: 'Dashboard', href: route('dashboard') }, { label: 'Manajemen Pengguna' }]" />
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 lg:flex-row lg:items-end lg:justify-between">
                <form class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_180px_180px_auto]" @submit.prevent="submit">
                    <TextInput v-model="form.q" type="search" maxlength="100" placeholder="Cari nama atau email" />
                    <FormSelect
                        v-model="form.role"
                        placeholder="Semua role"
                        :options="[
                            { label: 'Semua role', value: '' },
                            { label: 'Super Admin', value: 'super_admin' },
                            { label: 'Admin', value: 'admin' },
                            { label: 'Visitor', value: 'visitor' },
                        ]"
                    />
                    <FormSelect
                        v-model="form.status"
                        placeholder="Semua status"
                        :options="[
                            { label: 'Semua status', value: '' },
                            { label: 'Aktif', value: 'active' },
                            { label: 'Nonaktif', value: 'inactive' },
                        ]"
                    />
                    <SecondaryButton type="submit" :disabled="form.processing">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Filter
                    </SecondaryButton>
                </form>

                <Link
                    :href="route('super-admin.users.create')"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Buat Admin
                </Link>
            </div>

            <DataTable :columns="columns" :rows="users.data">
                <template #empty>
                    <EmptyState title="User tidak ditemukan" message="Coba ubah filter role, status, atau keyword pencarian." />
                </template>

                <template #cell-user="{ row }">
                    <div>
                        <p class="font-semibold text-ink">{{ row.name }}</p>
                        <p class="mt-1 text-sm text-ink-muted">{{ row.email }}</p>
                    </div>
                </template>

                <template #cell-role="{ row }">
                    <StatusBadge :value="row.role" />
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :value="row.is_active ? 'active' : 'inactive'" />
                </template>

                <template #cell-operational="{ row }">
                    <div class="text-sm text-ink-muted">
                        <p>{{ row.events_count }} event</p>
                        <p>{{ row.orders_count }} order</p>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="row.can_edit"
                            :href="route('super-admin.users.edit', row.id)"
                            aria-label="Edit user"
                            title="Edit user"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white text-ink transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <Edit class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">Edit user</span>
                        </Link>
                        <IconButton v-if="row.can_deactivate" label="Nonaktifkan user" @click="deactivateUser(row)">
                            <UserX class="h-4 w-4" aria-hidden="true" />
                        </IconButton>
                        <IconButton v-if="row.can_delete" label="Hapus user" variant="danger" @click="confirmDeleteUser(row)">
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                        </IconButton>
                        <span v-if="!row.can_edit && !row.can_deactivate && !row.can_delete" class="text-sm text-ink-muted">
                            Tidak ada aksi
                        </span>
                    </div>
                </template>
            </DataTable>

            <Pagination :links="users.links" />
        </div>

        <DeleteConfirmationModal
            :show="Boolean(userToDelete)"
            title="Hapus user?"
            :message="`Akun ${userToDelete?.name ?? ''} akan dihapus permanen dari sistem.`"
            confirm-label="Hapus User"
            :processing="deleteProcessing"
            @close="closeDeleteModal"
            @confirm="deleteUser"
        />
    </AuthenticatedLayout>
</template>
