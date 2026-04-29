<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Save, Settings, ShieldCheck } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    settings: {
        type: Array,
        required: true,
    },
    sensitiveKeywords: {
        type: Array,
        default: () => [],
    },
});

const initialSettings = Object.fromEntries(props.settings.map((setting) => [setting.key, setting.value ?? '']));

const form = useForm({
    settings: initialSettings,
});

const groupedSettings = computed(() => [
    {
        title: 'Umum',
        keys: ['site_name', 'site_tagline', 'public_gallery_per_page', 'dashboard_table_per_page'],
    },
    {
        title: 'Upload & Watermark',
        keys: ['upload_max_file_size_mb', 'upload_max_files_per_batch', 'watermark_text', 'watermark_opacity'],
    },
    {
        title: 'Order',
        keys: ['payment_pending_hours'],
    },
].map((group) => ({
    ...group,
    items: group.keys.map((key) => props.settings.find((setting) => setting.key === key)).filter(Boolean),
})));

const submit = () => {
    form.put(route('super-admin.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Settings</h1>
                <Breadcrumbs :items="[{ label: 'Dashboard', href: route('dashboard') }, { label: 'Settings' }]" />
            </div>
        </template>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-indigo-50 text-primary">
                        <ShieldCheck class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 class="font-heading text-lg font-semibold text-ink">Credential sensitif tetap di environment</h2>
                        <p class="mt-1 text-sm leading-6 text-ink-muted">
                            Server key, client key, token, password, dan credential pembayaran tidak disimpan di tabel settings.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="keyword in sensitiveKeywords"
                                :key="keyword"
                                class="rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-ink-muted ring-1 ring-border"
                            >
                                {{ keyword }}
                            </span>
                        </div>
                    </div>
                </div>
                <InputError class="mt-3" :message="form.errors.settings" />
            </section>

            <section v-for="group in groupedSettings" :key="group.title" class="rounded-lg border border-border bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-md bg-cyan-50 text-cyan-700">
                        <Settings class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <h2 class="font-heading text-lg font-semibold text-ink">{{ group.title }}</h2>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div v-for="setting in group.items" :key="setting.key">
                        <InputLabel :for="setting.key" :value="setting.label" />
                        <TextInput
                            :id="setting.key"
                            v-model="form.settings[setting.key]"
                            :type="setting.type"
                            class="mt-1 block w-full"
                            :min="setting.type === 'number' ? 0 : undefined"
                        />
                        <p class="mt-1 text-xs text-ink-muted">{{ setting.description }}</p>
                        <InputError class="mt-2" :message="form.errors[`settings.${setting.key}`]" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <PrimaryButton type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" aria-hidden="true" />
                    Simpan Settings
                </PrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
