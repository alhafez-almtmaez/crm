<script setup>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ConfirmPopup from 'primevue/confirmpopup';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import DataTable from '../../components/admin/DataTable.vue';
import PrimeFloatField from '../../components/form/PrimeFloatField.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useServerTable } from '../../composables/useServerTable';

const { t } = useI18n();
const appToast = useAppToast();
const confirm = useConfirm();
const {
    loading,
    rows,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows,
    onPageChange,
    onSortChange,
} = useServerTable({
    endpoint: '/admin/plan-weight-rules/records',
    defaultSortBy: 'priority',
    defaultSortDir: 'desc',
});

const dialogVisible = ref(false);
const editingRule = ref(null);
const saving = ref(false);
const errors = ref({});
const form = ref({
    name: '',
    pattern: '',
    keyword: '',
    weight: 1,
    is_standalone: false,
    min_pages: '',
    max_pages: '',
    priority: 0,
    is_active: true,
    classification: '',
});

const columns = computed(() => [
    { field: 'name', header: t('planWeightRules.name'), sortable: true },
    { field: 'weight', header: t('planWeightRules.weight'), sortable: true },
    { field: 'classification', header: t('planWeightRules.classification') },
    { field: 'priority', header: t('planWeightRules.priority'), sortable: true },
    { field: 'is_standalone_label', header: t('planWeightRules.isStandalone'), sortable: true, sortField: 'is_standalone' },
    { field: 'is_active_label', header: t('planWeightRules.isActive'), sortable: true, sortField: 'is_active' },
]);

const tableRows = computed(() => (rows.value ?? []).map((row) => ({
    ...row,
    is_standalone_label: row.is_standalone ? t('common.yes') : t('common.no'),
    is_active_label: row.is_active ? t('common.active') : t('common.inactive'),
})));

const resetForm = () => {
    form.value = {
        name: '',
        pattern: '',
        keyword: '',
        weight: 1,
        is_standalone: false,
        min_pages: '',
        max_pages: '',
        priority: 0,
        is_active: true,
        classification: '',
    };
    errors.value = {};
};

const openCreate = () => {
    editingRule.value = null;
    resetForm();
    dialogVisible.value = true;
};

const openEdit = (rule) => {
    editingRule.value = rule;
    form.value = {
        name: rule.name ?? '',
        pattern: rule.pattern ?? '',
        keyword: rule.keyword ?? '',
        weight: Number(rule.weight ?? 1),
        is_standalone: Boolean(rule.is_standalone),
        min_pages: rule.min_pages ?? '',
        max_pages: rule.max_pages ?? '',
        priority: Number(rule.priority ?? 0),
        is_active: Boolean(rule.is_active),
        classification: rule.classification ?? '',
    };
    errors.value = {};
    dialogVisible.value = true;
};

const payload = () => ({
    ...form.value,
    min_pages: form.value.min_pages === '' ? null : Number(form.value.min_pages),
    max_pages: form.value.max_pages === '' ? null : Number(form.value.max_pages),
    weight: Number(form.value.weight ?? 0),
    priority: Number(form.value.priority ?? 0),
});

const saveRule = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const request = editingRule.value
            ? axios.put(`/admin/plan-weight-rules/${editingRule.value.id}`, payload())
            : axios.post('/admin/plan-weight-rules', payload());
        const { data } = await request;

        appToast.success(data?.message ?? t('common.success'));
        dialogVisible.value = false;
        await fetchRows();
    } catch (error) {
        errors.value = error?.response?.data?.errors ?? {};
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('planWeightRules.saveFailed'),
        });
    } finally {
        saving.value = false;
    }
};

const askDelete = ({ data: rule, event }) => {
    confirm.require({
        target: event?.currentTarget ?? event?.target ?? document.body,
        message: t('planWeightRules.deleteConfirm', { name: rule.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('common.delete'),
            severity: 'danger',
        },
        accept: async () => {
            try {
                const { data } = await axios.delete(`/admin/plan-weight-rules/${rule.id}`);
                appToast.success(data?.message ?? t('planWeightRules.deleted'));
                await fetchRows();
            } catch (error) {
                appToast.fromAxiosError(error, {
                    summary: t('notifications.deleteFailedTitle'),
                    fallback: t('planWeightRules.deleteFailed'),
                });
            }
        },
    });
};

onMounted(() => {
    fetchRows();
});
</script>

<template>
    <Head :title="t('planWeightRules.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('planWeightRules.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <DataTable
                :columns="columns"
                :rows="tableRows"
                :loading="loading"
                :total-records="totalRecords"
                :current-page="currentPage"
                :rows-per-page="rowsPerPage"
                :search="search"
                :sort-field="sortBy"
                :sort-order="tableSortOrder"
                :create-label="t('planWeightRules.createRule')"
                :search-label="t('planWeightRules.searchRules')"
                :table-title="t('planWeightRules.tableTitle')"
                @update:search="search = $event"
                @page-change="onPageChange"
                @sort-change="onSortChange"
                @create="openCreate"
                @edit="openEdit"
                @delete="askDelete"
            />

            <ConfirmPopup />

            <Dialog
                v-model:visible="dialogVisible"
                modal
                :header="editingRule ? t('planWeightRules.editRule') : t('planWeightRules.createRule')"
                class="w-[min(680px,96vw)]"
            >
                <form class="grid gap-4" @submit.prevent="saveRule">
                    <div class="grid gap-4 md:grid-cols-2">
                        <PrimeFloatField
                            id="weight-rule-name"
                            v-model="form.name"
                            :label="t('planWeightRules.name')"
                            required
                            :error="errors.name?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-classification"
                            v-model="form.classification"
                            :label="t('planWeightRules.classification')"
                            :error="errors.classification?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-pattern"
                            v-model="form.pattern"
                            :label="t('planWeightRules.pattern')"
                            :error="errors.pattern?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-keyword"
                            v-model="form.keyword"
                            :label="t('planWeightRules.keyword')"
                            :error="errors.keyword?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-weight"
                            v-model="form.weight"
                            :label="t('planWeightRules.weight')"
                            input-type="number"
                            :input-props="{ min: '0', step: '0.25' }"
                            required
                            :error="errors.weight?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-priority"
                            v-model="form.priority"
                            :label="t('planWeightRules.priority')"
                            input-type="number"
                            :input-props="{ min: '0', step: '1' }"
                            required
                            :error="errors.priority?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-min-pages"
                            v-model="form.min_pages"
                            :label="t('planWeightRules.minPages')"
                            input-type="number"
                            :input-props="{ min: '1', step: '1' }"
                            :error="errors.min_pages?.[0]"
                        />
                        <PrimeFloatField
                            id="weight-rule-max-pages"
                            v-model="form.max_pages"
                            :label="t('planWeightRules.maxPages')"
                            input-type="number"
                            :input-props="{ min: '1', step: '1' }"
                            :error="errors.max_pages?.[0]"
                        />
                    </div>

                    <div class="flex flex-wrap gap-4 rounded-md border border-(--border) bg-(--muted)/30 p-3">
                        <label class="inline-flex items-center gap-2 text-sm font-medium">
                            <Checkbox v-model="form.is_standalone" binary />
                            {{ t('planWeightRules.isStandalone') }}
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-medium">
                            <Checkbox v-model="form.is_active" binary />
                            {{ t('planWeightRules.isActive') }}
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogVisible = false" />
                        <Button type="submit" :label="t('common.saveChanges')" :loading="saving" />
                    </div>
                </form>
            </Dialog>
        </section>
    </AdminLayout>
</template>
