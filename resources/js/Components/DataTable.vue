<script setup>
defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-border bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-left text-sm">
                <thead class="bg-surface text-xs font-semibold uppercase text-ink-muted">
                    <tr>
                        <th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3">
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length" class="px-4 py-10 text-center text-sm text-ink-muted">
                            <slot name="empty">Data belum tersedia.</slot>
                        </td>
                    </tr>
                    <tr v-for="(row, rowIndex) in rows" :key="row.id ?? rowIndex" class="hover:bg-surface">
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3 align-middle">
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
