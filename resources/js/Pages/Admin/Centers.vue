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
    rows: centers,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows: fetchCenters,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/centers/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});
const historyVisible = ref(false);
const historyEndpoint = ref('');
const historyEntityName = ref('');

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'name', header: t('centers.centerName'), sortable: true },
    { field: 'phone', header: t('centers.phone'), sortable: true, ltr: true },
    { field: 'working_days_display', header: t('centers.workingDays') },
    { field: 'created_at_formatted', header: t('centers.createdAt'), sortable: true, sortField: 'created_at' },
]);

const openCreate = () => {
    router.get('/admin/centers/create');
};

const openEdit = (center) => {
    router.get('/admin/centers/' + center.id + '/edit');
};

const deleteCenter = async (center) => {
    try {
        const { data } = await axios.delete('/admin/centers/' + center.id);
        appToast.success(data?.message ?? t('notifications.centerDeleted'));
        await fetchCenters();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('notifications.deleteCenterFailed'),
        });
    }
};

const askDeleteCenter = ({ data: center, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('centers.deleteConfirm', { name: center.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('centers.deleteCenter'),
            severity: 'danger',
        },
        accept: () => {
            deleteCenter(center);
        },
    });
};

const openHistory = (center) => {
    historyEntityName.value = center.name;
    historyEndpoint.value = `/admin/centers/${center.id}/activity-logs`;
    historyVisible.value = true;
};

onMounted(() => {
    fetchCenters();
});
</script>

<template>
    <Head :title="t('centers.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('centers.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="centers"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :create-label="t('centers.createCenter')"
                :search-label="t('centers.searchCenters')"
                :table-title="t('centers.tableTitle')"
                :show-history="true"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @history="openHistory"
                @edit="openEdit"
                @delete="askDeleteCenter"
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
