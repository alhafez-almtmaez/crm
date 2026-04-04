<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import PrimeDataTable from 'primevue/datatable';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import { computed } from 'vue';
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
    rowActions: {
        type: Array,
        default: () => [],
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

const emit = defineEmits(['create', 'delete', 'edit', 'history', 'rowAction', 'pageChange', 'sortChange', 'update:search']);
const resolvedCreateLabel = () => props.createLabel || t('common.create');
const resolvedSearchLabel = () => props.searchLabel || t('common.search');
const resolvedTableTitle = () => props.tableTitle || t('common.records');
const resolvedEmptyMessage = () => props.emptyMessage || t('common.noRecords');
const resolveBadgeClass = (column, rowData) => {
    if (typeof column.badgeClassField === 'string' && column.badgeClassField !== '') {
        return rowData?.[column.badgeClassField] ?? '';
    }

    return '';
};
const normalizedRowActions = computed(() => (props.rowActions ?? []).filter((action) => action && action.key));
const actionColumnWidth = computed(() => {
    const baseButtonsCount = 2 + (props.showHistory ? 1 : 0);
    const totalButtons = baseButtonsCount + normalizedRowActions.value.length;
    const width = Math.max(170, totalButtons * 48 + 20);

    return `${width}px`;
});
const shouldShowRowAction = (action, rowData) => {
    if (typeof action.show !== 'function') {
        return true;
    }

    return action.show(rowData);
};
const resolveRowActionTitle = (action, rowData) => {
    if (typeof action.title === 'function') {
        return action.title(rowData);
    }

    if (typeof action.title === 'string' && action.title !== '') {
        return action.title;
    }

    if (typeof action.titleKey === 'string' && action.titleKey !== '') {
        return t(action.titleKey);
    }

    return t('common.actions');
};

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
            >
                <template v-if="column.ltr" #body="{ data }">
                    <span dir="ltr" class="inline-block" style="unicode-bidi: plaintext">{{ data[column.field] }}</span>
                </template>
                <template v-else-if="column.badge" #body="{ data }">
                    <span
                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                        :class="resolveBadgeClass(column, data)"
                    >
                        {{ data[column.field] }}
                    </span>
                </template>
            </Column>

            <Column v-if="showActions" :header="t('common.actions')" :style="{ width: actionColumnWidth }">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button
                            v-for="action in normalizedRowActions"
                            v-show="shouldShowRowAction(action, data)"
                            :key="action.key + '-' + data.id"
                            size="small"
                            :severity="action.severity ?? 'secondary'"
                            :icon="action.icon"
                            :outlined="Boolean(action.outlined)"
                            :title="resolveRowActionTitle(action, data)"
                            :aria-label="resolveRowActionTitle(action, data)"
                            @click="emit('rowAction', { action: action.key, data, event: $event })"
                        />
                        <Button
                            v-if="showHistory"
                            size="small"
                            severity="secondary"
                            icon="pi pi-history"
                            :title="t('common.history')"
                            :aria-label="t('common.history')"
                            @click="emit('history', data)"
                        />
                        <Button
                            size="small"
                            severity="secondary"
                            icon="pi pi-pencil"
                            :title="t('common.edit')"
                            :aria-label="t('common.edit')"
                            @click="emit('edit', data)"
                        />
                        <Button
                            size="small"
                            severity="danger"
                            icon="pi pi-trash"
                            :title="t('common.delete')"
                            :aria-label="t('common.delete')"
                            @click="emit('delete', { data, event: $event })"
                        />
                    </div>
                </template>
            </Column>
        </PrimeDataTable>
    </article>
</template>
