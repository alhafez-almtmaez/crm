<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AbsenceMessageLogDialog from '../../components/admin/AbsenceMessageLogDialog.vue';
import AbsenceAlertPreviewDialog from '../../components/admin/AbsenceAlertPreviewDialog.vue';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import DataTable from '../../components/admin/DataTable.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useServerTable } from '../../composables/useServerTable';

const confirm = useConfirm();
const appToast = useAppToast();
const { t } = useI18n();
const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
});
const previewVisible = ref(false);
const previewLoading = ref(false);
const previewMessages = ref([]);
const messageLogVisible = ref(false);
const messageLogLoading = ref(false);
const messageLogResendingId = ref(null);
const messageLogs = ref([]);
const messageLogEvaluation = ref(null);
const filtersVisible = ref(false);
const defaultFilters = () => ({
    center_id: null,
    date_from: '',
    date_to: '',
    alert_status: null,
});
const filters = ref(defaultFilters());
const appliedFilters = ref(defaultFilters());
const filterParams = () => Object.entries(appliedFilters.value).reduce((params, [key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
        params[key] = value;
    }

    return params;
}, {});
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
    extraParams: filterParams,
});

const parseYmdDate = (value) => {
    if (!value || typeof value !== 'string') {
        return null;
    }

    const parts = value.split('-').map((segment) => Number(segment));
    if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
        return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
};

const formatYmdDate = (value) => {
    if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
        return '';
    }

    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const filterDateFromValue = computed({
    get: () => parseYmdDate(filters.value.date_from),
    set: (value) => {
        filters.value.date_from = formatYmdDate(value);
    },
});

const filterDateToValue = computed({
    get: () => parseYmdDate(filters.value.date_to),
    set: (value) => {
        filters.value.date_to = formatYmdDate(value);
    },
});

const alertStatusFilterOptions = computed(() => [
    { label: t('evaluations.alertsSent'), value: 'sent' },
    { label: t('evaluations.alertsPending'), value: 'pending' },
]);
const draftFilterCount = computed(() => Object.values(filters.value).filter((value) => value !== null && value !== undefined && value !== '').length);
const activeFilterCount = computed(() => Object.values(appliedFilters.value).filter((value) => value !== null && value !== undefined && value !== '').length);
const canClearFilters = computed(() => draftFilterCount.value > 0 || activeFilterCount.value > 0);
const filterButtonLabel = computed(() => (activeFilterCount.value > 0
    ? t('evaluations.filters.toggleWithCount', { count: activeFilterCount.value })
    : t('evaluations.filters.toggle')));

const rows = computed(() => (sourceRows.value ?? []).map((row) => {
    const hasLocalPreview = Number(row.local_absence_preview_count ?? 0) > 0;

    return {
        ...row,
        center_name: row.center_name ?? t('common.na'),
        admin_name: row.admin_name ?? t('common.na'),
        alert_status_label: row.is_send_absence_alerts
            ? t('evaluations.alertsSent')
            : hasLocalPreview
                ? t('evaluations.preview.status')
                : t('evaluations.alertsPending'),
        alert_status_badge_class: row.is_send_absence_alerts
            ? 'bg-emerald-700 text-white'
            : hasLocalPreview
                ? 'bg-sky-700 text-white'
                : 'bg-amber-600 text-white',
    };
}));

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

const messageLogSeverity = (row) => ({
    failed: 'danger',
    sent: 'success',
}[row.message_log_status] ?? 'secondary');

const rowActions = computed(() => [
    {
        key: 'open-report',
        icon: 'pi pi-external-link',
        severity: 'info',
        outlined: true,
        titleKey: 'evaluations.openReport',
        show: (row) => Boolean(row.report_url),
    },
    {
        key: 'message-logs',
        icon: 'pi pi-whatsapp',
        severity: messageLogSeverity,
        outlined: true,
        titleKey: 'evaluations.messageLog.open',
    },
    {
        key: 'send-alerts',
        icon: 'pi pi-bell',
        severity: (row) => row.is_send_absence_alerts ? 'secondary' : 'warning',
        outlined: true,
        disabled: (row) => Boolean(row.is_send_absence_alerts),
        titleKey: 'evaluations.sendAlerts',
    },
    {
        key: 'preview-alerts',
        icon: 'pi pi-eye',
        severity: 'info',
        outlined: true,
        titleKey: 'evaluations.preview.open',
        show: (row) => Number(row.local_absence_preview_count ?? 0) > 0,
    },
]);

const openCreate = () => {
    router.get('/admin/evaluations/create');
};

const openEdit = (row) => {
    router.get(`/admin/evaluations/${row.id}/edit`);
};

const openReport = (row) => {
    if (!row.report_url) {
        return;
    }

    window.open(row.report_url, '_blank', 'noopener');
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
        if (data?.meta?.local_preview) {
            previewLoading.value = false;
            previewMessages.value = data.meta.preview_messages ?? [];
            previewVisible.value = true;
        }
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('evaluations.sendAlertsFailed'),
        });
    }
};

const fetchAlertPreviews = async (row) => {
    previewVisible.value = true;
    previewLoading.value = true;
    previewMessages.value = [];

    try {
        const { data } = await axios.get(`/admin/evaluations/${row.id}/absence-alert-previews`);
        previewMessages.value = data?.data ?? [];
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.loadFailedTitle'),
            fallback: t('evaluations.preview.loadFailed'),
        });
    } finally {
        previewLoading.value = false;
    }
};

const fetchMessageLogs = async (row) => {
    messageLogVisible.value = true;
    messageLogLoading.value = true;
    messageLogs.value = [];
    messageLogEvaluation.value = row;

    try {
        const { data } = await axios.get(`/admin/evaluations/${row.id}/message-logs`);
        messageLogs.value = data?.data ?? [];
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.loadFailedTitle'),
            fallback: t('evaluations.messageLog.loadFailed'),
        });
    } finally {
        messageLogLoading.value = false;
    }
};

const replaceMessageLog = (nextLog) => {
    const index = messageLogs.value.findIndex((log) => log.id === nextLog.id);

    if (index === -1) {
        messageLogs.value = [nextLog, ...messageLogs.value];
        return;
    }

    messageLogs.value = messageLogs.value.map((log, logIndex) => (logIndex === index ? nextLog : log));
};

const resendMessageLog = async (log) => {
    if (!messageLogEvaluation.value?.id || !log?.id) {
        return;
    }

    messageLogResendingId.value = log.id;

    try {
        const { data } = await axios.post(`/admin/evaluations/${messageLogEvaluation.value.id}/message-logs/${log.id}/resend`);
        if (data?.data) {
            replaceMessageLog(data.data);
        }
        appToast.success(data?.message ?? t('evaluations.messageLog.resendSuccess'));
        await fetchRows();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('evaluations.messageLog.resendFailed'),
        });
    } finally {
        messageLogResendingId.value = null;
    }
};

const toggleFilters = () => {
    filtersVisible.value = !filtersVisible.value;
};

const applyFilters = async () => {
    appliedFilters.value = { ...filters.value };
    currentPage.value = 1;
    await fetchRows();
};

const clearFilters = async () => {
    filters.value = defaultFilters();
    appliedFilters.value = defaultFilters();
    currentPage.value = 1;
    await fetchRows();
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
    if (action === 'open-report') {
        openReport(row);
        return;
    }

    if (action === 'preview-alerts') {
        fetchAlertPreviews(row);
        return;
    }

    if (action === 'message-logs') {
        fetchMessageLogs(row);
        return;
    }

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
                :show-filters="filtersVisible"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @edit="openEdit"
                @delete="askDelete"
                @row-action="handleRowAction"
            >
                <template #toolbar-actions>
                    <Button
                        type="button"
                        icon="pi pi-filter"
                        severity="secondary"
                        outlined
                        :label="filterButtonLabel"
                        class="h-11 px-4 text-base font-semibold"
                        :aria-expanded="filtersVisible"
                        @click="toggleFilters"
                    />
                </template>

                <template #filters>
                    <form class="flex flex-col gap-3 xl:flex-row xl:items-start" @submit.prevent="applyFilters">
                        <div class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <FloatLabel variant="on">
                                <Select
                                    input-id="evaluations-filter-center"
                                    v-model="filters.center_id"
                                    :options="props.centers"
                                    option-label="name"
                                    option-value="id"
                                    filter
                                    show-clear
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <label for="evaluations-filter-center">{{ t('evaluations.filters.center') }}</label>
                            </FloatLabel>

                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="evaluations-filter-date-from"
                                    v-model="filterDateFromValue"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <label for="evaluations-filter-date-from">{{ t('evaluations.filters.dateFrom') }}</label>
                            </FloatLabel>

                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="evaluations-filter-date-to"
                                    v-model="filterDateToValue"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <label for="evaluations-filter-date-to">{{ t('evaluations.filters.dateTo') }}</label>
                            </FloatLabel>

                            <FloatLabel variant="on">
                                <Select
                                    input-id="evaluations-filter-alert-status"
                                    v-model="filters.alert_status"
                                    :options="alertStatusFilterOptions"
                                    option-label="label"
                                    option-value="value"
                                    show-clear
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <label for="evaluations-filter-alert-status">{{ t('evaluations.filters.alertStatus') }}</label>
                            </FloatLabel>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                icon="pi pi-filter-slash"
                                severity="secondary"
                                outlined
                                :label="t('evaluations.filters.clear')"
                                :disabled="!canClearFilters"
                                class="h-11 shrink-0"
                                @click="clearFilters"
                            />
                            <Button
                                type="submit"
                                icon="pi pi-check"
                                :label="t('evaluations.filters.apply')"
                                class="h-11 shrink-0"
                            />
                        </div>
                    </form>
                </template>
            </DataTable>
            <AbsenceAlertPreviewDialog
                v-model="previewVisible"
                :messages="previewMessages"
                :loading="previewLoading"
            />
            <AbsenceMessageLogDialog
                v-model="messageLogVisible"
                :evaluation="messageLogEvaluation"
                :logs="messageLogs"
                :loading="messageLogLoading"
                :resending-log-id="messageLogResendingId"
                @resend="resendMessageLog"
            />
            <ConfirmPopup />
        </section>
    </AdminLayout>
</template>
