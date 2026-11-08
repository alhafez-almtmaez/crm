<script setup>
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import DataTable from '../../components/admin/DataTable.vue';
import HomeworkCertificatePrintDialog from '../../components/admin/HomeworkCertificatePrintDialog.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useServerTable } from '../../composables/useServerTable';

const confirm = useConfirm();
const appToast = useAppToast();
const { t } = useI18n();
const page = usePage();
const props = defineProps({
    filterOptions: { type: Object, default: () => ({}) },
});

const emptyFilters = () => ({ dates: null, center_id: null, group_id: null, admin_id: null });
const filters = ref(emptyFilters());
const appliedFilters = ref({});
const filterCenters = computed(() => props.filterOptions.centers ?? []);
const filterGroups = computed(() => {
    const groups = props.filterOptions.groups ?? [];
    if (filters.value.center_id !== null) {
        return groups.filter((group) => group.center_id === filters.value.center_id);
    }

    const names = new Map(filterCenters.value.map((center) => [center.id, center.name]));
    return groups.map((group) => ({
        ...group,
        name: names.has(group.center_id) ? `${group.name} · ${names.get(group.center_id)}` : group.name,
    }));
});
const hasFilters = computed(() => Object.values(filters.value).some((value) => value !== null)
    || Object.keys(appliedFilters.value).length > 0);

watch(() => filters.value.center_id, () => {
    if (!filterGroups.value.some((group) => group.id === filters.value.group_id)) {
        filters.value.group_id = null;
    }
});

const deliveringHomeworkIds = ref(new Set());
const printingHomework = ref(null);
const canPrintCertificates = computed(() => (page.props.auth?.user?.roles ?? []).includes('admin')
    || (page.props.auth?.user?.permissions ?? []).includes('students.update'));
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
    defaultSortBy: 'date',
    defaultSortDir: 'desc',
    extraParams: () => appliedFilters.value,
});

const dateParameter = (value) => {
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return `${value.getFullYear()}-${month}-${day}`;
};

const applyFilters = () => {
    const { dates, center_id, group_id, admin_id } = filters.value;
    const [from, to] = dates ?? [];
    appliedFilters.value = {
        ...(from ? { date_from: dateParameter(from), date_to: dateParameter(to ?? from) } : {}),
        ...(center_id ? { center_id } : {}),
        ...(group_id ? { group_id } : {}),
        ...(admin_id && props.filterOptions.can_filter_by_admin ? { admin_id } : {}),
    };
    currentPage.value = 1;
    return fetchRows();
};

const clearFilters = () => {
    filters.value = emptyFilters();
    return applyFilters();
};

const rows = computed(() => (sourceRows.value ?? []).map((row) => ({
    ...row,
    center_name: row.center_name ?? t('common.na'),
    group_name: row.group_name ?? t('common.na'),
    admin_name: row.admin_name ?? t('common.na'),
    certificate_print_status: Number(row.printed_certificates_count) > 0
        ? t('homeworks.certificatePrintedCount', { count: row.printed_certificates_count })
        : t('homeworks.certificateNotPrinted'),
    certificate_print_status_class: Number(row.printed_certificates_count) > 0
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
        : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
})));

const columns = computed(() => [
    { field: 'date_formatted', header: t('homeworks.date'), sortable: true, sortField: 'date' },
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'center_name', header: t('homeworks.center'), sortable: true, sortField: 'center_name' },
    { field: 'group_name', header: t('homeworks.group'), sortable: true, sortField: 'group_name' },
    { field: 'admin_name', header: t('students.admin'), sortable: true, sortField: 'admin_name' },
    { field: 'students_count', header: t('homeworks.studentsCount') },
    { field: 'completed_points_count', header: t('homeworks.completedPointsCount') },
    ...(canPrintCertificates.value ? [{
        field: 'certificate_print_status',
        header: t('homeworks.certificatePrintStatus'),
        badge: true,
        badgeClassField: 'certificate_print_status_class',
    }] : []),
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
    ...(canPrintCertificates.value ? [{
        key: 'print-certificates',
        icon: 'pi pi-verified',
        severity: 'secondary',
        outlined: true,
        title: t('homeworks.printDueCertificates'),
    }] : []),
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
    if (action === 'print-certificates') {
        printingHomework.value = data;
        return;
    }

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
            >
                <template #filters>
                    <form class="space-y-3" @submit.prevent="applyFilters">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="min-w-0 space-y-1">
                                <label for="homeworks-filter-date" class="block text-sm font-semibold">{{ t('homeworks.filters.date') }}</label>
                                <DatePicker
                                    v-model="filters.dates"
                                    input-id="homeworks-filter-date"
                                    selection-mode="range"
                                    date-format="dd/mm/yy"
                                    :manual-input="false"
                                    :placeholder="t('homeworks.filters.datePlaceholder')"
                                    show-icon
                                    icon-display="input"
                                    show-button-bar
                                    fluid
                                />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <label for="homeworks-filter-center" class="block text-sm font-medium">{{ t('homeworks.center') }}</label>
                                <Select
                                    v-model="filters.center_id"
                                    input-id="homeworks-filter-center"
                                    :options="filterCenters"
                                    option-label="name"
                                    option-value="id"
                                    :placeholder="t('homeworks.filters.allCenters')"
                                    filter
                                    show-clear
                                    fluid
                                />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <label for="homeworks-filter-group" class="block text-sm font-medium">{{ t('homeworks.group') }}</label>
                                <Select
                                    v-model="filters.group_id"
                                    input-id="homeworks-filter-group"
                                    :options="filterGroups"
                                    option-label="name"
                                    option-value="id"
                                    :placeholder="t('homeworks.filters.allGroups')"
                                    filter
                                    show-clear
                                    fluid
                                />
                            </div>
                            <div v-if="filterOptions.can_filter_by_admin" class="min-w-0 space-y-1">
                                <label for="homeworks-filter-admin" class="block text-sm font-medium">{{ t('students.admin') }}</label>
                                <Select
                                    v-model="filters.admin_id"
                                    input-id="homeworks-filter-admin"
                                    :options="filterOptions.admins ?? []"
                                    option-label="name"
                                    option-value="id"
                                    :placeholder="t('homeworks.filters.allAdmins')"
                                    filter
                                    show-clear
                                    fluid
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                icon="pi pi-filter-slash"
                                severity="secondary"
                                outlined
                                :label="t('homeworks.filters.clear')"
                                :disabled="loading || !hasFilters"
                                @click="clearFilters"
                            />
                            <Button type="submit" icon="pi pi-filter" :label="t('homeworks.filters.apply')" :loading="loading" />
                        </div>
                    </form>
                </template>
            </DataTable>
            <ConfirmPopup />
            <HomeworkCertificatePrintDialog
                v-if="printingHomework"
                :homework="printingHomework"
                @close="printingHomework = null"
                @printed="fetchRows()"
            />
        </section>
    </AdminLayout>
</template>
