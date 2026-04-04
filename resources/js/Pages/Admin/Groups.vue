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
    rows: groups,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows: fetchGroups,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/groups/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});
const historyVisible = ref(false);
const historyEndpoint = ref('');
const historyEntityName = ref('');

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'name', header: t('groups.groupName'), sortable: true },
    { field: 'center_name', header: t('groups.center'), sortable: true, sortField: 'center_name' },
    { field: 'created_at_formatted', header: t('groups.createdAt'), sortable: true, sortField: 'created_at' },
]);

const openCreate = () => {
    router.get('/admin/groups/create');
};

const openEdit = (group) => {
    router.get('/admin/groups/' + group.id + '/edit');
};

const deleteGroup = async (group) => {
    try {
        const { data } = await axios.delete('/admin/groups/' + group.id);
        appToast.success(data?.message ?? t('notifications.groupDeleted'));
        await fetchGroups();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('notifications.deleteGroupFailed'),
        });
    }
};

const askDeleteGroup = ({ data: group, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('groups.deleteConfirm', { name: group.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('groups.deleteGroup'),
            severity: 'danger',
        },
        accept: () => {
            deleteGroup(group);
        },
    });
};

const openHistory = (group) => {
    historyEntityName.value = group.name;
    historyEndpoint.value = `/admin/groups/${group.id}/activity-logs`;
    historyVisible.value = true;
};

onMounted(() => {
    fetchGroups();
});
</script>

<template>
    <Head :title="t('groups.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('groups.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="groups"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :create-label="t('groups.createGroup')"
                :search-label="t('groups.searchGroups')"
                :table-title="t('groups.tableTitle')"
                :show-history="true"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @history="openHistory"
                @edit="openEdit"
                @delete="askDeleteGroup"
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
