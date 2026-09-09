<script setup>
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/vue3';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted, ref } from 'vue';
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
const page = usePage();
const deliveringHomeworkIds = ref(new Set());
const canDeliverCertificates = computed(() => {
    const roles = page.props.auth?.user?.roles ?? [];
    const permissions = page.props.auth?.user?.permissions ?? [];

    return roles.includes('admin')
        || (permissions.includes('students.update') && permissions.includes('certificates.send'));
});
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
    endpoint: '/admin/homeworks/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});

const rows = computed(() => (sourceRows.value ?? []).map((row) => ({
    ...row,
    center_name: row.center_name ?? t('common.na'),
    group_name: row.group_name ?? t('common.na'),
    admin_name: row.admin_name ?? t('common.na'),
})));

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'date_formatted', header: t('homeworks.date'), sortable: true, sortField: 'date' },
    { field: 'center_name', header: t('homeworks.center'), sortable: true, sortField: 'center_name' },
    { field: 'group_name', header: t('homeworks.group'), sortable: true, sortField: 'group_name' },
    { field: 'admin_name', header: t('students.admin'), sortable: true, sortField: 'admin_name' },
    { field: 'students_count', header: t('homeworks.studentsCount') },
    { field: 'completed_points_count', header: t('homeworks.completedPointsCount') },
    { field: 'created_at_formatted', header: t('homeworks.createdAt'), sortable: true, sortField: 'created_at' },
]);

const isDeliveringCertificates = (homeworkId) => deliveringHomeworkIds.value.has(Number(homeworkId));

const certificateDeliveryTitle = (row) => {
    if (isDeliveringCertificates(row.id)) {
        return t('homeworks.certificateDeliveryInProgress');
    }

    if (Number(row.completed_points_count ?? 0) === 0) {
        return t('homeworks.noCompletedPointsForCertificates');
    }

    return t('homeworks.deliverDueCertificates');
};

const rowActions = computed(() => [
    ...(canDeliverCertificates.value ? [{
        key: 'deliver-certificates',
        icon: 'pi pi-whatsapp',
        severity: 'success',
        outlined: true,
        loading: (row) => isDeliveringCertificates(row.id),
        disabled: (row) => isDeliveringCertificates(row.id)
            || Number(row.completed_points_count ?? 0) === 0,
        title: certificateDeliveryTitle,
    }] : []),
    {
        key: 'pdf',
        icon: 'pi pi-file-pdf',
        severity: 'secondary',
        title: t('homeworks.exportPdf'),
    },
]);

const openCreate = () => {
    router.get('/admin/daily-follow-up', { section: 'homework' });
};

const openEdit = (row) => {
    router.get('/admin/daily-follow-up', {
        center_id: row.center_id,
        group_id: row.group_id,
        date: String(row.date ?? '').slice(0, 10),
        section: 'homework',
    });
};

const openPdf = (row) => {
    if (!row?.id) {
        return;
    }

    window.open(`/admin/homeworks/${row.id}/pdf`, '_blank', 'noopener');
};

const handleRowAction = ({ action, data, event }) => {
    if (action === 'deliver-certificates') {
        askCertificateDelivery(data, event);
        return;
    }

    if (action === 'pdf') {
        openPdf(data);
    }
};

const setCertificatesDelivering = (homeworkId, delivering) => {
    const nextIds = new Set(deliveringHomeworkIds.value);

    if (delivering) {
        nextIds.add(Number(homeworkId));
    } else {
        nextIds.delete(Number(homeworkId));
    }

    deliveringHomeworkIds.value = nextIds;
};

const deliverCertificates = async (row) => {
    if (!row?.id || isDeliveringCertificates(row.id)) {
        return;
    }

    setCertificatesDelivering(row.id, true);

    try {
        const { data } = await axios.post(`/admin/homeworks/${row.id}/certificates/deliver`);

        if (data?.meta?.has_issues) {
            appToast.push({
                severity: 'warn',
                summary: t('homeworks.certificateDeliveryFinishedWithIssues'),
                detail: data?.message ?? t('homeworks.certificateDeliveryFailed'),
                life: 6000,
            });
        } else if (Number(data?.meta?.candidates ?? 0) === 0) {
            appToast.info(data?.message ?? t('homeworks.noDueCertificates'));
        } else {
            appToast.success(data?.message ?? t('homeworks.certificateDeliverySuccess'), undefined, 5000);
        }

        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('homeworks.certificateDeliveryFailed'),
            life: 5000,
        });
    } finally {
        setCertificatesDelivering(row.id, false);
    }
};

const askCertificateDelivery = (row, event) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('homeworks.certificateDeliveryConfirm', {
            group: row.group_name,
            date: row.date_formatted,
        }),
        icon: 'pi pi-whatsapp',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('homeworks.deliverDueCertificates'),
            severity: 'success',
        },
        accept: () => {
            deliverCertificates(row);
        },
    });
};

const deleteRow = async (row) => {
    try {
        const { data } = await axios.delete(`/admin/homeworks/${row.id}`);
        appToast.success(data?.message ?? t('homeworks.deleted'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('homeworks.deleteFailed'),
        });
    }
};

const askDelete = ({ data: row, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('homeworks.deleteConfirm', {
            center: row.center_name,
            group: row.group_name,
            date: row.date_formatted,
        }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('homeworks.deleteHomework'),
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
    <Head :title="t('homeworks.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('homeworks.title')">
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
                :row-actions="rowActions"
                :create-label="t('homeworks.createHomework')"
                :search-label="t('homeworks.searchHomeworks')"
                :table-title="t('homeworks.tableTitle')"
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
