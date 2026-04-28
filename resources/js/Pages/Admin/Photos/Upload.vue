<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, FileImage, UploadCloud, XCircle } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    limits: {
        type: Object,
        default: () => ({ max_file_size_mb: 15, max_files_per_batch: 50 }),
    },
});

const page = usePage();
const fileInput = ref(null);
const selectedFiles = ref([]);

const form = useForm({
    event_id: '',
    photos: [],
});

const eventOptions = computed(() =>
    props.events.map((event) => ({
        label: `${event.name} - ${formatDate(event.date)}`,
        value: event.id,
    })),
);

const uploadResult = computed(() => page.props.flash?.upload_result ?? null);

function formatDate(value) {
    return value
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(value))
        : 'Tanggal kosong';
}

const formatBytes = (bytes) => {
    if (!bytes) {
        return '0 KB';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
};

const chooseFiles = (event) => {
    selectedFiles.value = Array.from(event.target.files || []);
    form.photos = selectedFiles.value;
};

const submit = () => {
    form.post(route('admin.photos.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.photos = [];
            selectedFiles.value = [];

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-heading text-xl font-semibold text-ink">Upload Foto</h1>
                <p class="mt-1 text-sm text-ink-muted">Upload batch ke event milik Anda dan buat preview watermark otomatis.</p>
            </div>
        </template>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
            <form class="space-y-5 rounded-lg border border-border bg-white p-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="event_id" value="Event" />
                    <select
                        id="event_id"
                        v-model="form.event_id"
                        class="mt-2 min-h-10 w-full rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary"
                        required
                    >
                        <option value="" disabled>Pilih event</option>
                        <option v-for="option in eventOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.event_id" />
                </div>

                <div>
                    <InputLabel for="photos" value="File Foto" />
                    <label
                        for="photos"
                        class="mt-2 flex min-h-56 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-border bg-surface px-5 py-8 text-center transition hover:border-primary hover:bg-indigo-50/60"
                    >
                        <UploadCloud class="h-10 w-10 text-primary" aria-hidden="true" />
                        <span class="mt-3 text-sm font-semibold text-ink">Pilih foto jpeg, png, atau webp</span>
                        <span class="mt-1 text-sm text-ink-muted">
                            Maksimal {{ limits.max_files_per_batch }} file per batch, {{ limits.max_file_size_mb }} MB per file
                        </span>
                    </label>
                    <input
                        id="photos"
                        ref="fileInput"
                        class="sr-only"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        required
                        @change="chooseFiles"
                    />
                    <InputError class="mt-2" :message="form.errors.photos" />
                    <InputError class="mt-2" :message="form.errors['photos.0']" />
                </div>

                <div v-if="selectedFiles.length" class="rounded-lg border border-border">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <p class="text-sm font-semibold text-ink">{{ selectedFiles.length }} file dipilih</p>
                        <p class="text-sm text-ink-muted">Siap upload</p>
                    </div>
                    <ul class="max-h-72 divide-y divide-border overflow-y-auto">
                        <li v-for="file in selectedFiles" :key="`${file.name}-${file.size}`" class="flex items-center gap-3 px-4 py-3">
                            <FileImage class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-ink" :title="file.name">{{ file.name }}</p>
                                <p class="mt-1 text-xs text-ink-muted">{{ formatBytes(file.size) }} - {{ file.type }}</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-if="form.progress" class="rounded-lg border border-border bg-surface p-4">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-semibold text-ink">Mengupload</span>
                        <span class="text-ink-muted">{{ form.progress.percentage }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-end">
                    <Link :href="route('admin.photos.index')">
                        <SecondaryButton type="button" class="w-full sm:w-auto">Batal</SecondaryButton>
                    </Link>
                    <PrimaryButton type="submit" :disabled="form.processing || !selectedFiles.length">
                        <UploadCloud class="h-4 w-4" aria-hidden="true" />
                        {{ form.processing ? 'Mengupload...' : 'Upload Foto' }}
                    </PrimaryButton>
                </div>
            </form>

            <aside class="space-y-5">
                <section class="rounded-lg border border-border bg-white p-5">
                    <h2 class="font-heading text-base font-semibold text-ink">Batas Upload</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">File per batch</dt>
                            <dd class="font-semibold text-ink">{{ limits.max_files_per_batch }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Ukuran file</dt>
                            <dd class="font-semibold text-ink">{{ limits.max_file_size_mb }} MB</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Format</dt>
                            <dd class="font-semibold text-ink">JPG, PNG, WEBP</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="uploadResult" class="rounded-lg border border-border bg-white p-5">
                    <h2 class="font-heading text-base font-semibold text-ink">Hasil Upload</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-green-50 p-3 text-green-700">
                            <CheckCircle2 class="h-5 w-5" aria-hidden="true" />
                            <p class="mt-2 text-2xl font-semibold">{{ uploadResult.success_count }}</p>
                            <p class="text-xs font-semibold uppercase">Berhasil</p>
                        </div>
                        <div class="rounded-lg bg-red-50 p-3 text-red-700">
                            <XCircle class="h-5 w-5" aria-hidden="true" />
                            <p class="mt-2 text-2xl font-semibold">{{ uploadResult.failed_count }}</p>
                            <p class="text-xs font-semibold uppercase">Gagal</p>
                        </div>
                    </div>

                    <ul v-if="uploadResult.failed_files?.length" class="mt-4 space-y-2">
                        <li
                            v-for="file in uploadResult.failed_files"
                            :key="file.filename"
                            class="rounded-md border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            <span class="font-semibold">{{ file.filename }}</span>
                            <span class="block">{{ file.error }}</span>
                        </li>
                    </ul>
                </section>

                <section v-if="events.length === 0" class="rounded-lg border border-border bg-white p-5">
                    <EmptyState title="Belum ada event" message="Buat event dulu sebelum mengunggah foto." />
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
