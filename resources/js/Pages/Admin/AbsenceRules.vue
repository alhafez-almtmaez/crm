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
    endpoint: '/admin/absence-rules/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});

const attendanceLabel = (type) => {
    if (type === 'absence') {
        return t('absenceRules.attendanceAbsence');
    }

    if (type === 'excused_absence') {
        return t('absenceRules.attendanceExcusedAbsence');
    }

    return type;
};

const actionLabel = (value) => {
    if (value === 'freeze_student' || value === 'send_message' || value === 'send_message_and_freeze') {
        return t('absenceRules.actionFreezeStudent');
    }

    if (value === 'dismiss_student') {
        return t('absenceRules.actionDismissStudent');
    }

    return value;
};

const rows = computed(() => (sourceRows.value ?? []).map((row) => ({
    ...row,
    center_name_display: row.center_name ?? t('absenceRules.allCenters'),
    attendance_type_label: attendanceLabel(row.attendance_type),
    action_label: actionLabel(row.action),
    is_active_label: row.is_active ? t('absenceRules.active') : t('absenceRules.inactive'),
    is_active_badge_class: row.is_active ? 'bg-emerald-700 text-white' : 'bg-red-700 text-white',
    message_template_name_display: row.message_template_name ?? t('absenceRules.noTemplate'),
})));

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'center_name_display', header: t('absenceRules.center'), sortable: true, sortField: 'center_name' },
    { field: 'attendance_type_label', header: t('absenceRules.attendanceType'), sortable: true, sortField: 'attendance_type' },
    { field: 'occurrence_number', header: t('absenceRules.occurrenceNumber'), sortable: true },
    { field: 'action_label', header: t('absenceRules.action'), sortable: true, sortField: 'action' },
    { field: 'deduction_points_count', header: t('absenceRules.deductionPointsCount'), sortable: true, sortField: 'deduction_points_count' },
    { field: 'message_template_name_display', header: t('absenceRules.messageTemplate') },
    { field: 'is_active_label', header: t('absenceRules.status'), sortable: true, sortField: 'is_active', badge: true, badgeClassField: 'is_active_badge_class' },
    { field: 'created_at_formatted', header: t('absenceRules.createdAt'), sortable: true, sortField: 'created_at' },
]);

const openCreate = () => {
    router.get('/admin/absence-rules/create');
};

const openEdit = (row) => {
    router.get(`/admin/absence-rules/${row.id}/edit`);
};

const deleteRow = async (row) => {
    try {
        const { data } = await axios.delete(`/admin/absence-rules/${row.id}`);
        appToast.success(data?.message ?? t('absenceRules.deleted'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('absenceRules.deleteFailed'),
        });
    }
};

const askDelete = ({ data: row, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('absenceRules.deleteConfirm', {
            center: row.center_name_display,
            occurrence: row.occurrence_number,
        }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('absenceRules.deleteRule'),
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
    <Head :title="t('absenceRules.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('absenceRules.title')">
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
                :create-label="t('absenceRules.createRule')"
                :search-label="t('absenceRules.searchRules')"
                :table-title="t('absenceRules.tableTitle')"
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
