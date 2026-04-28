<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FormSelect from '@/Components/FormSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    managedUser: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.managedUser.name,
    email: props.managedUser.email,
    password: '',
    password_confirmation: '',
    is_active: props.managedUser.is_active,
});

const submit = () => {
    form.put(route('super-admin.users.update', props.managedUser.id));
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Edit Admin</h1>
                <p class="mt-1 text-sm text-ink-muted">Admin hanya dapat diubah sebelum memiliki event atau order.</p>
            </div>
        </template>

        <form class="max-w-2xl space-y-5 rounded-lg border border-border bg-white p-5" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nama Admin" />
                <TextInput id="name" v-model="form.name" class="mt-2 w-full" maxlength="100" required autofocus />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-2 w-full" maxlength="150" required />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="is_active" value="Status Akun" />
                <FormSelect
                    id="is_active"
                    v-model="form.is_active"
                    class="mt-2 w-full"
                    :options="[
                        { label: 'Aktif', value: true },
                        { label: 'Nonaktif', value: false },
                    ]"
                />
                <InputError class="mt-2" :message="form.errors.is_active" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="password" value="Password Baru" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-2 w-full" />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Konfirmasi Password Baru" />
                    <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-2 w-full" />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <Link :href="route('super-admin.users.index')">
                    <SecondaryButton type="button" class="w-full sm:w-auto">Batal</SecondaryButton>
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" aria-hidden="true" />
                    Simpan Perubahan
                </PrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
