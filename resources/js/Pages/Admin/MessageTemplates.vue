<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import DataTable from '../../components/admin/DataTable.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useServerTable } from '../../composables/useServerTable';

const confirm = useConfirm();
const appToast = useAppToast();
const { t } = useI18n();
const {
    loading,
    rows: sourceRows,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/message-templates/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});

const rows = computed(() => (sourceRows.value ?? []).map((row) => ({
    ...row,
    is_active_label: row.is_active ? t('messageTemplates.active') : t('messageTemplates.inactive'),
    is_active_badge_class: row.is_active ? 'bg-emerald-700 text-white' : 'bg-red-700 text-white',
})));

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'name', header: t('messageTemplates.templateName'), sortable: true },
    { field: 'key', header: t('messageTemplates.key'), sortable: true },
    { field: 'locale', header: t('messageTemplates.locale'), sortable: true },
    { field: 'is_active_label', header: t('messageTemplates.status'), sortable: true, sortField: 'is_active', badge: true, badgeClassField: 'is_active_badge_class' },
    { field: 'created_at_formatted', header: t('messageTemplates.createdAt'), sortable: true, sortField: 'created_at' },
]);

const openCreate = () => {
    router.get('/admin/message-templates/create');
};

const openEdit = (row) => {
    router.get(`/admin/message-templates/${row.id}/edit`);
};

const deleteRow = async (row) => {
    try {
        const { data } = await axios.delete(`/admin/message-templates/${row.id}`);
        appToast.success(data?.message ?? t('messageTemplates.deleted'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('messageTemplates.deleteFailed'),
        });
    }
};

const askDelete = ({ data: row, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('messageTemplates.deleteConfirm', { name: row.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('messageTemplates.deleteTemplate'),
            severity: 'danger',
        },
        accept: () => {
            deleteRow(row);
        },
    });
};

onMounted(() => {
    fetchRows();
});
</script>

<template>
    <Head :title="t('messageTemplates.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('messageTemplates.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :create-label="t('messageTemplates.createTemplate')"
                :search-label="t('messageTemplates.searchTemplates')"
                :table-title="t('messageTemplates.tableTitle')"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @edit="openEdit"
                @delete="askDelete"
            />
            <ConfirmPopup />
        </section>
    </AdminLayout>
</template>
