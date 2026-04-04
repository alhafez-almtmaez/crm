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
    rows: users,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows: fetchUsers,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/users/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});
const historyVisible = ref(false);
const historyEndpoint = ref('');
const historyEntityName = ref('');

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'name', header: t('users.name'), sortable: true },
    { field: 'email', header: t('auth.email'), sortable: true },
    { field: 'role_name', header: t('users.role') },
    { field: 'created_at_formatted', header: t('users.createdAt'), sortable: true, sortField: 'created_at' },
]);

const openCreate = () => {
    router.get('/admin/users/create');
};

const openEdit = (user) => {
    router.get('/admin/users/' + user.id + '/edit');
};

const deleteUser = async (user) => {
    try {
        const { data } = await axios.delete('/admin/users/' + user.id);
        appToast.success(data?.message ?? t('notifications.userDeleted'));
        await fetchUsers();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('notifications.deleteUserFailed'),
        });
    }
};

const askDeleteUser = ({ data: user, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('users.deleteConfirm', { name: user.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('users.deleteUser'),
            severity: 'danger',
        },
        accept: () => {
            deleteUser(user);
        },
    });
};

const openHistory = (user) => {
    historyEntityName.value = user.name;
    historyEndpoint.value = `/admin/users/${user.id}/activity-logs`;
    historyVisible.value = true;
};

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <Head :title="t('users.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('users.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="users"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :create-label="t('users.createUser')"
                :search-label="t('users.searchUsers')"
                :table-title="t('users.tableTitle')"
                :show-history="true"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @history="openHistory"
                @edit="openEdit"
                @delete="askDeleteUser"
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
