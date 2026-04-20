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
    endpoint: '/admin/evaluations/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});

const rows = computed(() => (sourceRows.value ?? []).map((row) => ({
    ...row,
    center_name: row.center_name ?? t('common.na'),
    admin_name: row.admin_name ?? t('common.na'),
    alert_status_label: row.is_send_absence_alerts ? t('evaluations.alertsSent') : t('evaluations.alertsPending'),
    alert_status_badge_class: row.is_send_absence_alerts
        ? 'bg-emerald-700 text-white'
        : 'bg-amber-600 text-white',
})));

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'date_formatted', header: t('evaluations.date'), sortable: true, sortField: 'date' },
    { field: 'center_name', header: t('evaluations.center'), sortable: true, sortField: 'center_name' },
    { field: 'admin_name', header: t('students.admin'), sortable: true, sortField: 'admin_name' },
    {
        field: 'alert_status_label',
        header: t('evaluations.status'),
        sortable: true,
        sortField: 'is_send_absence_alerts',
        badge: true,
        badgeClassField: 'alert_status_badge_class',
    },
    { field: 'created_at_formatted', header: t('evaluations.createdAt'), sortable: true, sortField: 'created_at' },
]);

const rowActions = computed(() => [
    {
        key: 'send-alerts',
        icon: 'pi pi-bell',
        severity: 'warning',
        outlined: true,
        titleKey: 'evaluations.sendAlerts',
        show: (row) => !row.is_send_absence_alerts,
    },
]);

const openCreate = () => {
    router.get('/admin/evaluations/create');
};

const openEdit = (row) => {
    router.get(`/admin/evaluations/${row.id}/edit`);
};

const deleteRow = async (row) => {
    try {
        const { data } = await axios.delete(`/admin/evaluations/${row.id}`);
        appToast.success(data?.message ?? t('evaluations.deleted'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('evaluations.deleteFailed'),
        });
    }
};

const sendAlerts = async (row) => {
    try {
        const { data } = await axios.post(`/admin/evaluations/${row.id}/absence-alerts`);
        appToast.success(data?.message ?? t('evaluations.sendAlerts'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('evaluations.sendAlertsFailed'),
        });
    }
};

const askDelete = ({ data: row, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('evaluations.deleteConfirm', {
            center: row.center_name,
            date: row.date_formatted,
        }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('evaluations.deleteEvaluation'),
            severity: 'danger',
        },
        accept: () => {
            deleteRow(row);
        },
    });
};

const handleRowAction = ({ action, data: row, event }) => {
    if (action !== 'send-alerts') {
        return;
    }

    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('evaluations.sendAlertsConfirm'),
        icon: 'pi pi-send',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('evaluations.sendAlerts'),
        },
        accept: () => {
            sendAlerts(row);
        },
    });
};

onMounted(() => {
    fetchRows();
});
</script>

<template>
    <Head :title="t('evaluations.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('evaluations.title')">
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
                :create-label="t('evaluations.createEvaluation')"
                :search-label="t('evaluations.searchEvaluations')"
                :table-title="t('evaluations.tableTitle')"
                :row-actions="rowActions"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @edit="openEdit"
                @delete="askDelete"
                @row-action="handleRowAction"
            />
            <ConfirmPopup />
        </section>
    </AdminLayout>
</template>
