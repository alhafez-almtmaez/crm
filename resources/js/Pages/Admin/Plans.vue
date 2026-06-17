<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import DataTable from '../../components/admin/DataTable.vue';
import EntityActivityDrawer from '../../components/admin/EntityActivityDrawer.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useServerTable } from '../../composables/useServerTable';

const confirm = useConfirm();
const appToast = useAppToast();
const { t } = useI18n();
const {
    loading,
    rows: plans,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows: fetchPlans,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/plans/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});
const historyVisible = ref(false);
const historyEndpoint = ref('');
const historyEntityName = ref('');
const pointsVisible = ref(false);
const selectedPlan = ref(null);
const pointsImportFile = ref(null);
const pointsImportErrors = ref({});
const actionLoading = ref(false);

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'name', header: t('plans.planName'), sortable: true },
    { field: 'points_count', header: t('plans.pointsCount') },
    { field: 'created_at_formatted', header: t('plans.createdAt'), sortable: true, sortField: 'created_at' },
]);

const rowActions = computed(() => [
    {
        key: 'points',
        icon: 'pi pi-file-excel',
        severity: 'info',
        title: t('plans.pointsActions'),
    },
]);

const openCreate = () => {
    router.get('/admin/plans/create');
};

const openEdit = (plan) => {
    router.get('/admin/plans/' + plan.id + '/edit');
};

const deletePlan = async (plan) => {
    try {
        const { data } = await axios.delete('/admin/plans/' + plan.id);
        appToast.success(data?.message ?? t('notifications.planDeleted'));
        await fetchPlans();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('notifications.deletePlanFailed'),
        });
    }
};

const askDeletePlan = ({ data: plan, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('plans.deleteConfirm', { name: plan.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('plans.deletePlan'),
            severity: 'danger',
        },
        accept: () => {
            deletePlan(plan);
        },
    });
};

const openHistory = (plan) => {
    historyEntityName.value = plan.name;
    historyEndpoint.value = `/admin/plans/${plan.id}/activity-logs`;
    historyVisible.value = true;
};

const openPointsDialog = (plan) => {
    selectedPlan.value = plan;
    pointsImportFile.value = null;
    pointsImportErrors.value = {};
    pointsVisible.value = true;
};

const runPointsExport = () => {
    if (!selectedPlan.value) {
        return;
    }

    window.open(`/admin/plans/${selectedPlan.value.id}/points/export`, '_blank');
};

const onPointsImportFileChange = (event) => {
    pointsImportFile.value = event?.target?.files?.[0] ?? null;
};

const submitPointsImport = async () => {
    pointsImportErrors.value = {};

    if (!selectedPlan.value) {
        return;
    }

    if (!pointsImportFile.value) {
        pointsImportErrors.value = {
            file: [t('plans.importFileRequired')],
        };
        return;
    }

    actionLoading.value = true;

    try {
        const formData = new FormData();
        formData.append('file', pointsImportFile.value);

        const { data } = await axios.post(`/admin/plans/${selectedPlan.value.id}/points/import`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        appToast.success(data?.message ?? t('plans.importSuccess'));
        pointsVisible.value = false;
        await fetchPlans();
    } catch (error) {
        pointsImportErrors.value = error?.response?.data?.errors ?? {};
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('plans.importFailed'),
        });
    } finally {
        actionLoading.value = false;
    }
};

const handleRowAction = (payload) => {
    if (payload.action === 'points') {
        openPointsDialog(payload.data);
    }
};

onMounted(() => {
    fetchPlans();
});
</script>

<template>
    <Head :title="t('plans.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('plans.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="plans"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :create-label="t('plans.createPlan')"
                :search-label="t('plans.searchPlans')"
                :table-title="t('plans.tableTitle')"
                :show-history="true"
                :row-actions="rowActions"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @history="openHistory"
                @edit="openEdit"
                @delete="askDeletePlan"
                @row-action="handleRowAction"
            />
            <ConfirmPopup />
            <EntityActivityDrawer
                v-model="historyVisible"
                :endpoint="historyEndpoint"
                :entity-name="historyEntityName"
            />

            <Dialog
                v-model:visible="pointsVisible"
                modal
                :header="t('plans.pointsActions') + (selectedPlan ? ` - ${selectedPlan.name}` : '')"
                :style="{ width: 'min(36rem, 96vw)' }"
            >
                <div class="grid gap-5">
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            icon="pi pi-download"
                            severity="secondary"
                            :label="t('plans.exportPoints')"
                            :disabled="!selectedPlan"
                            @click="runPointsExport"
                        />
                    </div>

                    <form class="grid gap-4" @submit.prevent="submitPointsImport">
                        <div class="flex flex-col gap-2">
                            <label for="plans-points-import-file" class="text-sm font-medium">{{ t('plans.importFile') }}</label>
                            <input
                                id="plans-points-import-file"
                                type="file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                class="w-full rounded-md border border-(--border) bg-(--background) px-3 py-2 text-sm"
                                @change="onPointsImportFileChange"
                            >
                            <small class="text-xs text-(--muted-foreground)">{{ t('plans.importHint') }}</small>
                            <small v-if="pointsImportErrors.file" class="text-sm text-red-600">{{ pointsImportErrors.file[0] }}</small>
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="pointsVisible = false" />
                            <Button type="submit" icon="pi pi-upload" :label="t('plans.importPoints')" :loading="actionLoading" />
                        </div>
                    </form>
                </div>
            </Dialog>
        </section>
    </AdminLayout>
</template>
