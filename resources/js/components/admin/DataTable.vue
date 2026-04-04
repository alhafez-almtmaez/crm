<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import PrimeDataTable from 'primevue/datatable';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    createLabel: {
        type: String,
        default: '',
    },
    showCreate: {
        type: Boolean,
        default: true,
    },
    showActions: {
        type: Boolean,
        default: true,
    },
    showHistory: {
        type: Boolean,
        default: false,
    },
    currentPage: {
        type: Number,
        default: 1,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    rowsPerPage: {
        type: Number,
        default: 10,
    },
    searchLabel: {
        type: String,
        default: '',
    },
    search: {
        type: String,
        default: '',
    },
    tableTitle: {
        type: String,
        default: '',
    },
    totalRecords: {
        type: Number,
        default: 0,
    },
    sortField: {
        type: String,
        default: null,
    },
    sortOrder: {
        type: Number,
        default: null,
    },
    emptyMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['create', 'delete', 'edit', 'history', 'pageChange', 'sortChange', 'update:search']);
const resolvedCreateLabel = () => props.createLabel || t('common.create');
const resolvedSearchLabel = () => props.searchLabel || t('common.search');
const resolvedTableTitle = () => props.tableTitle || t('common.records');
const resolvedEmptyMessage = () => props.emptyMessage || t('common.noRecords');

const handlePage = (event) => {
    emit('pageChange', {
        page: event.page + 1,
        rows: event.rows,
    });
};

const handleSort = (event) => {
    emit('sortChange', {
        sortField: event.sortField ?? null,
        sortOrder: event.sortOrder ?? null,
    });
};
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-4 text-(--card-foreground) shadow-(--shadow-sm) sm:p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-xl font-semibold sm:text-2xl">{{ resolvedTableTitle() }}</h3>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <FloatLabel variant="on" class="w-full sm:w-72">
                    <InputText
                        input-id="table-search"
                        :model-value="search"
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-base text-(--foreground) shadow-none"
                        @update:model-value="emit('update:search', $event)"
                    />
                    <label for="table-search">{{ resolvedSearchLabel() }}</label>
                </FloatLabel>
                <Button
                    v-if="showCreate"
                    :label="resolvedCreateLabel()"
                    icon="pi pi-plus"
                    size="small"
                    class="h-11 px-4 text-base font-semibold"
                    @click="emit('create')"
                />
            </div>
        </div>

        <PrimeDataTable
            :value="rows"
            :loading="loading"
            data-key="id"
            paginator
            lazy
            sort-mode="single"
            removable-sort
            :rows="rowsPerPage"
            :first="(currentPage - 1) * rowsPerPage"
            :total-records="totalRecords"
            :sort-field="sortField"
            :sort-order="sortOrder"
            :rows-per-page-options="[10, 20, 50]"
            table-style="min-width: 50rem"
            @page="handlePage"
            @sort="handleSort"
        >
            <template #empty>
                <div class="py-8 text-center text-sm text-(--muted-foreground)">
                    {{ resolvedEmptyMessage() }}
                </div>
            </template>

            <Column
                v-for="column in columns"
                :key="column.field"
                :field="column.field"
                :header="column.header"
                :sortable="Boolean(column.sortable)"
                :sort-field="column.sortField ?? column.field"
            />

            <Column v-if="showActions" :header="t('common.actions')" :style="{ width: showHistory ? '220px' : '170px' }">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button
                            v-if="showHistory"
                            size="small"
                            severity="secondary"
                            icon="pi pi-history"
                            @click="emit('history', data)"
                        />
                        <Button size="small" severity="secondary" icon="pi pi-pencil" @click="emit('edit', data)" />
                        <Button
                            size="small"
                            severity="danger"
                            icon="pi pi-trash"
                            @click="emit('delete', { data, event: $event })"
                        />
                    </div>
                </template>
            </Column>
        </PrimeDataTable>
    </article>
</template>
