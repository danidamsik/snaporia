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

const form = useForm({
    name: '',
    description: '',
    date: '',
    location: '',
    price_per_photo: '',
    price_package: '',
    is_published: false,
});

const submit = () => {
    form.post(route('admin.events.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Buat Event</h1>
                <p class="mt-1 text-sm text-ink-muted">Tentukan informasi event, harga satuan, dan harga paket.</p>
            </div>
        </template>

        <form class="max-w-3xl space-y-5 rounded-lg border border-border bg-white p-5" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nama Event" />
                <TextInput id="name" v-model="form.name" class="mt-2 w-full" maxlength="150" required autofocus />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="description" value="Deskripsi" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    class="mt-2 w-full rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="date" value="Tanggal Event" />
                    <TextInput id="date" v-model="form.date" type="date" class="mt-2 w-full" />
                    <InputError class="mt-2" :message="form.errors.date" />
                </div>

                <div>
                    <InputLabel for="location" value="Lokasi" />
                    <TextInput id="location" v-model="form.location" class="mt-2 w-full" maxlength="255" />
                    <InputError class="mt-2" :message="form.errors.location" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="price_per_photo" value="Harga Satuan" />
                    <TextInput id="price_per_photo" v-model="form.price_per_photo" type="number" min="0" step="1000" class="mt-2 w-full" required />
                    <InputError class="mt-2" :message="form.errors.price_per_photo" />
                </div>

                <div>
                    <InputLabel for="price_package" value="Harga Paket" />
                    <TextInput id="price_package" v-model="form.price_package" type="number" min="0" step="1000" class="mt-2 w-full" required />
                    <InputError class="mt-2" :message="form.errors.price_package" />
                </div>
            </div>

            <div>
                <InputLabel for="is_published" value="Status Publikasi" />
                <FormSelect
                    id="is_published"
                    v-model="form.is_published"
                    class="mt-2 w-full"
                    :options="[
                        { label: 'Draft', value: false },
                        { label: 'Published', value: true },
                    ]"
                />
                <InputError class="mt-2" :message="form.errors.is_published" />
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <Link :href="route('admin.events.index')">
                    <SecondaryButton type="button" class="w-full sm:w-auto">Batal</SecondaryButton>
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" aria-hidden="true" />
                    Simpan Event
                </PrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
