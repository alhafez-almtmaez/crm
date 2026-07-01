<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
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
    endpoint: '/admin/monthly-plans/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});

const monthLabel = (month) => t(`monthlyPlans.months.${month}`);

const rows = computed(() => (sourceRows.value ?? []).map((row) => ({
    ...row,
    center_name: row.center_name || t('common.na'),
    group_name: row.group_name || t('common.na'),
    month_label: monthLabel(row.month),
})));

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'center_name', header: t('monthlyPlans.center'), sortable: true, sortField: 'center_name' },
    { field: 'group_name', header: t('monthlyPlans.group'), sortable: true, sortField: 'group_name' },
    { field: 'month_label', header: t('monthlyPlans.month'), sortable: true, sortField: 'month' },
    { field: 'year', header: t('monthlyPlans.year'), sortable: true },
    { field: 'students_count', header: t('monthlyPlans.studentsCount'), sortable: true },
    { field: 'generated_items_count', header: t('monthlyPlans.itemsCount'), sortable: true },
    { field: 'generated_at_formatted', header: t('monthlyPlans.generatedAt'), sortable: true, sortField: 'generated_at' },
]);

const openCreate = () => {
    router.get('/admin/monthly-plans/create');
};

const openEdit = (row) => {
    router.get(`/admin/monthly-plans/${row.id}/edit`);
};

const deleteRow = async (row) => {
    try {
        const { data } = await axios.delete(`/admin/monthly-plans/${row.id}`);
        appToast.success(data?.message ?? t('monthlyPlans.deleted'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('monthlyPlans.deleteFailed'),
        });
    }
};

const askDelete = ({ data: row, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('monthlyPlans.deleteConfirm', {
            group: row.group_name,
            month: row.month_label,
            year: row.year,
        }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('monthlyPlans.deletePlan'),
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
    <Head :title="t('monthlyPlans.savedPlans')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('monthlyPlans.savedPlans')">
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
                :create-label="t('monthlyPlans.createPlan')"
                :search-label="t('monthlyPlans.searchSavedPlans')"
                :table-title="t('monthlyPlans.savedPlans')"
                :empty-message="t('monthlyPlans.noSavedPlans')"
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
