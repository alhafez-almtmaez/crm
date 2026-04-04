<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import DataTable from '../../components/admin/DataTable.vue';
import { useServerTable } from '../../composables/useServerTable';

const { t } = useI18n();
const {
    loading,
    rows: activityLogs,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows: fetchActivityLogs,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/activity-logs/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'description', header: t('activityLogs.description'), sortable: true },
    { field: 'event', header: t('activityLogs.event'), sortable: true },
    { field: 'subject_display', header: t('activityLogs.subject') },
    { field: 'causer_display', header: t('activityLogs.causer') },
    { field: 'created_at_formatted', header: t('activityLogs.createdAt'), sortable: true, sortField: 'created_at' },
]);

onMounted(() => {
    fetchActivityLogs();
});
</script>

<template>
    <Head :title="t('activityLogs.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('activityLogs.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="activityLogs"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :show-create="false"
                :show-actions="false"
                :search-label="t('activityLogs.searchLogs')"
                :table-title="t('activityLogs.tableTitle')"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
            />
        </section>
    </AdminLayout>
</template>

