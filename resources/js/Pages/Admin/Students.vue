<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import IntlTelInput from 'intl-tel-input/vueWithUtils';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
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
const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
});
const {
    loading,
    rows: students,
    totalRecords,
    currentPage,
    rowsPerPage,
    search,
    sortBy,
    tableSortOrder,
    fetchRows: fetchStudents,
    onPageChange: handlePageChange,
    onSortChange: handleSortChange,
} = useServerTable({
    endpoint: '/admin/students/records',
    defaultSortBy: 'id',
    defaultSortDir: 'desc',
});
const historyVisible = ref(false);
const historyEndpoint = ref('');
const historyEntityName = ref('');
const freezeVisible = ref(false);
const congratulatoryVisible = ref(false);
const exportVisible = ref(false);
const importVisible = ref(false);
const selectedStudent = ref(null);
const actionLoading = ref(false);
const freezeErrors = ref({});
const congratulatoryErrors = ref({});
const importErrors = ref({});
const exportCenterId = ref(null);
const importFile = ref(null);
const freezeForm = ref({
    from: '',
    to: '',
    reason: '',
    phone: '',
    parent_phone_number: '',
    phone_number: '',
});
const congratulatoryForm = ref({
    reason: '',
    parent_phone_number: '',
    phone_number: '',
});

const rows = computed(() => (students.value ?? []).map((student) => ({
    ...student,
    status_label: student.is_active === 2
        ? t('students.statusFrozen')
        : (student.is_active === 0 ? t('students.statusInactive') : t('students.statusActive')),
    status_badge_class: student.is_active === 2
        ? 'bg-amber-700 text-white'
        : (student.is_active === 0
            ? 'bg-red-700 text-white'
            : 'bg-emerald-700 text-white'),
})));

const columns = computed(() => [
    { field: 'id', header: t('common.id'), sortable: true },
    { field: 'full_name', header: t('students.studentName'), sortable: true, sortField: 'full_name' },
    { field: 'plan_name', header: t('students.plan'), sortable: true, sortField: 'plan_name' },
    { field: 'center_name', header: t('groups.center'), sortable: true, sortField: 'center_name' },
    { field: 'group_name', header: t('students.group'), sortable: true, sortField: 'group_name' },
    { field: 'admin_name', header: t('students.admin'), sortable: true, sortField: 'admin_name' },
    { field: 'parent_phone_number', header: t('students.parentPhone'), sortable: true, sortField: 'parent_phone_number', ltr: true },
    { field: 'phone_number', header: t('students.phone'), sortable: true, sortField: 'phone_number', ltr: true },
    { field: 'status_label', header: t('students.status'), sortable: true, sortField: 'is_active', badge: true, badgeClassField: 'status_badge_class' },
]);

const rowActions = computed(() => [
    {
        key: 'freeze',
        icon: 'pi pi-lock',
        severity: 'warn',
        title: t('students.freezeStudent'),
        show: (student) => Number(student.is_active) !== 2,
    },
    {
        key: 'unfreeze',
        icon: 'pi pi-lock-open',
        severity: 'success',
        title: t('students.unfreezeStudent'),
        show: (student) => Number(student.is_active) === 2,
    },
    {
        key: 'congratulatory',
        icon: 'pi pi-send',
        severity: 'info',
        title: t('students.congratulatory'),
    },
]);
const phoneOptions = {
    initialCountry: 'jo',
    preferredCountries: ['jo', 'sa', 'ae', 'eg'],
    allowDropdown: true,
    separateDialCode: true,
    nationalMode: false,
    strictMode: true,
};
const telInputClass = 'h-11 w-full rounded-md border border-(--border) bg-(--background) px-3 text-left text-(--foreground) shadow-none';
const telInputProps = (id) => ({
    id,
    class: telInputClass,
    autocomplete: 'tel',
    placeholder: t('students.phonePlaceholder'),
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

const freezeFromDate = computed({
    get: () => parseYmdDate(freezeForm.value.from),
    set: (value) => {
        freezeForm.value.from = formatYmdDate(value);
    },
});

const freezeToDate = computed({
    get: () => parseYmdDate(freezeForm.value.to),
    set: (value) => {
        freezeForm.value.to = formatYmdDate(value);
    },
});

const openCreate = () => {
    router.get('/admin/students/create');
};

const onFreezeParentPhoneChange = (value) => {
    freezeForm.value.parent_phone_number = value ?? '';
};

const onFreezePhoneChange = (value) => {
    freezeForm.value.phone_number = value ?? '';
};

const onFreezeContactPhoneChange = (value) => {
    freezeForm.value.phone = value ?? '';
};

const onCongratsParentPhoneChange = (value) => {
    congratulatoryForm.value.parent_phone_number = value ?? '';
};

const onCongratsPhoneChange = (value) => {
    congratulatoryForm.value.phone_number = value ?? '';
};

const openExportDialog = () => {
    exportCenterId.value = null;
    exportVisible.value = true;
};

const runExport = () => {
    const params = new URLSearchParams();
    if (exportCenterId.value) {
        params.set('center_id', String(exportCenterId.value));
    }

    const query = params.toString();
    const url = `/admin/students/export${query !== '' ? `?${query}` : ''}`;
    window.open(url, '_blank');
    exportVisible.value = false;
};

const openImportDialog = () => {
    importFile.value = null;
    importErrors.value = {};
    importVisible.value = true;
};

const onImportFileChange = (event) => {
    const file = event?.target?.files?.[0] ?? null;
    importFile.value = file;
};

const submitImport = async () => {
    importErrors.value = {};

    if (!importFile.value) {
        importErrors.value = {
            file: [t('students.importFileRequired')],
        };
        return;
    }

    actionLoading.value = true;

    try {
        const formData = new FormData();
        formData.append('file', importFile.value);

        const { data } = await axios.post('/admin/students/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        appToast.success(data?.message ?? t('students.importSuccess'));
        importVisible.value = false;
        await fetchStudents();
    } catch (error) {
        importErrors.value = error?.response?.data?.errors ?? {};
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('students.importFailed'),
        });
    } finally {
        actionLoading.value = false;
    }
};

const openEdit = (student) => {
    router.get('/admin/students/' + student.id + '/edit');
};

const deleteStudent = async (student) => {
    try {
        const { data } = await axios.delete('/admin/students/' + student.id);
        appToast.success(data?.message ?? t('notifications.studentDeleted'));
        await fetchStudents();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('notifications.deleteStudentFailed'),
        });
    }
};

const askDeleteStudent = ({ data: student, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('students.deleteConfirm', { name: student.full_name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('students.deleteStudent'),
            severity: 'danger',
        },
        accept: () => {
            deleteStudent(student);
        },
    });
};

const openFreezeDialog = (student) => {
    selectedStudent.value = student;
    freezeErrors.value = {};
    const today = formatYmdDate(new Date());
    freezeForm.value = {
        from: today,
        to: today,
        reason: '',
        phone: '',
        parent_phone_number: student.parent_phone_number ?? '',
        phone_number: student.phone_number ?? '',
    };
    freezeVisible.value = true;
};

const openCongratulatoryDialog = (student) => {
    selectedStudent.value = student;
    congratulatoryErrors.value = {};
    congratulatoryForm.value = {
        reason: '',
        parent_phone_number: student.parent_phone_number ?? '',
        phone_number: student.phone_number ?? '',
    };
    congratulatoryVisible.value = true;
};

const submitFreeze = async () => {
    if (!selectedStudent.value) {
        return;
    }

    actionLoading.value = true;
    freezeErrors.value = {};

    try {
        const { data } = await axios.post(`/admin/students/${selectedStudent.value.id}/freeze`, freezeForm.value);
        appToast.success(data?.message ?? t('students.frozenSuccess'));
        freezeVisible.value = false;
        await fetchStudents();
    } catch (error) {
        freezeErrors.value = error?.response?.data?.errors ?? {};
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('students.freezeFailed'),
        });
    } finally {
        actionLoading.value = false;
    }
};

const submitCongratulatory = async () => {
    if (!selectedStudent.value) {
        return;
    }

    actionLoading.value = true;
    congratulatoryErrors.value = {};

    try {
        const { data } = await axios.post(`/admin/students/${selectedStudent.value.id}/congratulatory`, congratulatoryForm.value);
        appToast.success(data?.message ?? t('students.congratulatorySent'));
        congratulatoryVisible.value = false;
        await fetchStudents();
    } catch (error) {
        congratulatoryErrors.value = error?.response?.data?.errors ?? {};
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('students.congratulatoryFailed'),
        });
    } finally {
        actionLoading.value = false;
    }
};

const unfreezeStudent = async (student) => {
    actionLoading.value = true;

    try {
        const { data } = await axios.post(`/admin/students/${student.id}/unfreeze`);
        appToast.success(data?.message ?? t('students.unfrozenSuccess'));
        await fetchStudents();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('students.unfreezeFailed'),
        });
    } finally {
        actionLoading.value = false;
    }
};

const askUnfreezeStudent = ({ data: student, event }) => {
    const target = event?.currentTarget ?? event?.target ?? document.body;

    confirm.require({
        target,
        message: t('students.unfreezeConfirm', { name: student.full_name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('students.unfreezeStudent'),
            severity: 'success',
        },
        accept: () => {
            unfreezeStudent(student);
        },
    });
};

const handleRowAction = (payload) => {
    if (!payload?.action) {
        return;
    }

    if (payload.action === 'freeze') {
        openFreezeDialog(payload.data);
        return;
    }

    if (payload.action === 'unfreeze') {
        askUnfreezeStudent(payload);
        return;
    }

    if (payload.action === 'congratulatory') {
        openCongratulatoryDialog(payload.data);
    }
};

const openHistory = (student) => {
    historyEntityName.value = student.full_name;
    historyEndpoint.value = `/admin/students/${student.id}/activity-logs`;
    historyVisible.value = true;
};

onMounted(() => {
    fetchStudents();
});
</script>

<template>
    <Head :title="t('students.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('students.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button
                    type="button"
                    icon="pi pi-download"
                    severity="secondary"
                    :label="t('students.exportStudents')"
                    @click="openExportDialog"
                />
                <Button
                    type="button"
                    icon="pi pi-upload"
                    severity="secondary"
                    :label="t('students.importStudents')"
                    @click="openImportDialog"
                />
            </div>

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
                :create-label="t('students.createStudent')"
                :search-label="t('students.searchStudents')"
                :table-title="t('students.tableTitle')"
                :show-history="true"
                :row-actions="rowActions"
                @update:search="search = $event"
                @page-change="handlePageChange"
                @sort-change="handleSortChange"
                @create="openCreate"
                @history="openHistory"
                @edit="openEdit"
                @delete="askDeleteStudent"
                @row-action="handleRowAction"
            />
            <ConfirmPopup />
            <EntityActivityDrawer
                v-model="historyVisible"
                :endpoint="historyEndpoint"
                :entity-name="historyEntityName"
            />

            <Dialog
                v-model:visible="exportVisible"
                modal
                :header="t('students.exportStudents')"
                :style="{ width: 'min(30rem, 96vw)' }"
            >
                <div class="grid gap-4">
                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <Select
                                input-id="students-export-center"
                                v-model="exportCenterId"
                                :options="props.centers"
                                option-label="name"
                                option-value="id"
                                filter
                                show-clear
                                class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            />
                            <label for="students-export-center">{{ t('students.filterByCenter') }}</label>
                        </FloatLabel>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="exportVisible = false" />
                        <Button type="button" icon="pi pi-download" :label="t('students.exportNow')" @click="runExport" />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="importVisible"
                modal
                :header="t('students.importStudents')"
                :style="{ width: 'min(34rem, 96vw)' }"
            >
                <form class="grid gap-4" @submit.prevent="submitImport">
                    <div class="flex flex-col gap-2">
                        <label for="students-import-file" class="text-sm font-medium">{{ t('students.importFile') }}</label>
                        <input
                            id="students-import-file"
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="w-full rounded-md border border-(--border) bg-(--background) px-3 py-2 text-sm"
                            @change="onImportFileChange"
                        />
                        <small class="text-xs text-(--muted-foreground)">{{ t('students.importHint') }}</small>
                        <small v-if="importErrors.file" class="text-sm text-red-600">{{ importErrors.file[0] }}</small>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="importVisible = false" />
                        <Button type="submit" icon="pi pi-upload" :label="t('students.importNow')" :loading="actionLoading" />
                    </div>
                </form>
            </Dialog>

            <Dialog
                v-model:visible="freezeVisible"
                modal
                :header="t('students.freezeStudent') + (selectedStudent ? ` - ${selectedStudent.full_name}` : '')"
                :style="{ width: 'min(44rem, 96vw)' }"
            >
                <form class="grid gap-4" @submit.prevent="submitFreeze">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="freeze-from"
                                    v-model="freezeFromDate"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <label for="freeze-from">{{ t('students.freezeFrom') }}</label>
                            </FloatLabel>
                            <small v-if="freezeErrors.from" class="text-sm text-red-600">{{ freezeErrors.from[0] }}</small>
                        </div>

                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <DatePicker
                                    input-id="freeze-to"
                                    v-model="freezeToDate"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    :manual-input="false"
                                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                />
                                <label for="freeze-to">{{ t('students.freezeTo') }}</label>
                            </FloatLabel>
                            <small v-if="freezeErrors.to" class="text-sm text-red-600">{{ freezeErrors.to[0] }}</small>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label for="freeze-parent-phone" class="text-sm font-medium">{{ t('students.parentPhone') }}</label>
                            <IntlTelInput
                                v-model="freezeForm.parent_phone_number"
                                :options="phoneOptions"
                                :input-props="telInputProps('freeze-parent-phone')"
                                @change-number="onFreezeParentPhoneChange"
                            />
                            <small v-if="freezeErrors.parent_phone_number" class="text-sm text-red-600">{{ freezeErrors.parent_phone_number[0] }}</small>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label for="freeze-phone" class="text-sm font-medium">{{ t('students.phone') }}</label>
                            <IntlTelInput
                                v-model="freezeForm.phone_number"
                                :options="phoneOptions"
                                :input-props="telInputProps('freeze-phone')"
                                @change-number="onFreezePhoneChange"
                            />
                            <small v-if="freezeErrors.phone_number" class="text-sm text-red-600">{{ freezeErrors.phone_number[0] }}</small>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="freeze-contact-phone" class="text-sm font-medium">{{ t('students.freezeContactPhone') }}</label>
                        <IntlTelInput
                            v-model="freezeForm.phone"
                            :options="phoneOptions"
                            :input-props="telInputProps('freeze-contact-phone')"
                            @change-number="onFreezeContactPhoneChange"
                        />
                        <small v-if="freezeErrors.phone" class="text-sm text-red-600">{{ freezeErrors.phone[0] }}</small>
                    </div>

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <Textarea
                                id="freeze-reason"
                                v-model="freezeForm.reason"
                                rows="4"
                                class="w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            />
                            <label for="freeze-reason">{{ t('students.freezeReason') }}</label>
                        </FloatLabel>
                        <small v-if="freezeErrors.reason" class="text-sm text-red-600">{{ freezeErrors.reason[0] }}</small>
                    </div>

                    <div class="mt-2 flex justify-end gap-2">
                        <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="freezeVisible = false" />
                        <Button type="submit" :label="t('students.freezeStudent')" :loading="actionLoading" />
                    </div>
                </form>
            </Dialog>

            <Dialog
                v-model:visible="congratulatoryVisible"
                modal
                :header="t('students.congratulatory') + (selectedStudent ? ` - ${selectedStudent.full_name}` : '')"
                :style="{ width: 'min(44rem, 96vw)' }"
            >
                <form class="grid gap-4" @submit.prevent="submitCongratulatory">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label for="congrats-parent-phone" class="text-sm font-medium">{{ t('students.parentPhone') }}</label>
                            <IntlTelInput
                                v-model="congratulatoryForm.parent_phone_number"
                                :options="phoneOptions"
                                :input-props="telInputProps('congrats-parent-phone')"
                                @change-number="onCongratsParentPhoneChange"
                            />
                            <small v-if="congratulatoryErrors.parent_phone_number" class="text-sm text-red-600">{{ congratulatoryErrors.parent_phone_number[0] }}</small>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label for="congrats-phone" class="text-sm font-medium">{{ t('students.phone') }}</label>
                            <IntlTelInput
                                v-model="congratulatoryForm.phone_number"
                                :options="phoneOptions"
                                :input-props="telInputProps('congrats-phone')"
                                @change-number="onCongratsPhoneChange"
                            />
                            <small v-if="congratulatoryErrors.phone_number" class="text-sm text-red-600">{{ congratulatoryErrors.phone_number[0] }}</small>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <Textarea
                                id="congrats-reason"
                                v-model="congratulatoryForm.reason"
                                rows="6"
                                class="w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            />
                            <label for="congrats-reason">{{ t('students.congratulatoryReason') }}</label>
                        </FloatLabel>
                        <small v-if="congratulatoryErrors.reason" class="text-sm text-red-600">{{ congratulatoryErrors.reason[0] }}</small>
                    </div>

                    <div class="mt-2 flex justify-end gap-2">
                        <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="congratulatoryVisible = false" />
                        <Button type="submit" :label="t('students.sendCongratulatory')" :loading="actionLoading" />
                    </div>
                </form>
            </Dialog>
        </section>
    </AdminLayout>
</template>
