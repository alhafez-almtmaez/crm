<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ConfirmPopup from 'primevue/confirmpopup';
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

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'name', header: t('plans.planName'), sortable: true },
    { field: 'created_at_formatted', header: t('plans.createdAt'), sortable: true, sortField: 'created_at' },
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
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @history="openHistory"
                @edit="openEdit"
                @delete="askDeletePlan"
            />
            <ConfirmPopup />
            <EntityActivityDrawer
                v-model="historyVisible"
                :endpoint="historyEndpoint"
                :entity-name="historyEntityName"
            />
        </section>
    </AdminLayout>
</template>
