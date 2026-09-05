<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import DailyFollowUpStudentTable from '../../components/admin/DailyFollowUpStudentTable.vue';
import StudentFollowUpReportDialog from '../../components/admin/StudentFollowUpReportDialog.vue';
import FormFieldLabel from '../../components/form/FormFieldLabel.vue';

const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    selection: {
        type: Object,
        default: () => ({ center_id: null, group_id: null, date: '' }),
    },
    evaluation: {
        type: Object,
        default: () => ({ id: null, evaluation_type: 1, students: [], accessible: true }),
    },
    homework: {
        type: Object,
        default: () => ({ id: null, students: [], accessible: true }),
    },
    plan_context: {
        type: Object,
        default: () => ({ available: false, required: true, progress_available: false, monthly_plan: null, summary: null, students: [], create_url: null }),
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    active_section: {
        type: String,
        default: 'evaluation',
    },
    date_adjustment: {
        type: Object,
        default: null,
    },
});

const { t } = useI18n();
const selectedCenterId = ref(props.selection.center_id);
const selectedGroupId = ref(props.selection.group_id);
const selectedDate = ref(props.selection.date ?? '');
const loadingWorkspace = ref(false);
const reportVisible = ref(false);
const reportStudent = ref(null);
const reportUrl = ref('');
const dayOptions = [
    { value: 'sunday', dayIndex: 0, labelKey: 'days.sunday' },
    { value: 'monday', dayIndex: 1, labelKey: 'days.monday' },
    { value: 'tuesday', dayIndex: 2, labelKey: 'days.tuesday' },
    { value: 'wednesday', dayIndex: 3, labelKey: 'days.wednesday' },
    { value: 'thursday', dayIndex: 4, labelKey: 'days.thursday' },
    { value: 'friday', dayIndex: 5, labelKey: 'days.friday' },
    { value: 'saturday', dayIndex: 6, labelKey: 'days.saturday' },
];
const dayIndexByName = Object.fromEntries(dayOptions.map((day) => [day.value, day.dayIndex]));

const normalizeId = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const id = Number(value);

    return Number.isNaN(id) ? null : id;
};

const parseYmdDate = (value) => {
    if (typeof value !== 'string' || value === '') {
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

const selectedCenter = computed(() => {
    const centerId = normalizeId(selectedCenterId.value);

    return props.centers.find((item) => normalizeId(item?.id) === centerId) ?? null;
});

const groupOptions = computed(() => {
    const center = selectedCenter.value;

    return Array.isArray(center?.groups) ? center.groups : [];
});

const selectedGroup = computed(() => {
    const groupId = normalizeId(selectedGroupId.value);

    return groupOptions.value.find((group) => normalizeId(group?.id) === groupId) ?? null;
});

const selectedGroupWorkingDays = computed(() => {
    const groupWorkingDays = selectedGroup.value?.working_days;
    return Array.isArray(groupWorkingDays) ? groupWorkingDays : [];
});
const workingScheduleConfigured = computed(() => !selectedGroup.value || selectedGroupWorkingDays.value.length > 0);

const workingDayIndexes = computed(() => selectedGroupWorkingDays.value
    .map((day) => dayIndexByName[String(day).toLowerCase()])
    .filter((dayIndex) => Number.isInteger(dayIndex)));

const disabledWeekDays = computed(() => {
    if (!selectedGroup.value || workingDayIndexes.value.length === 0) {
        return [];
    }

    return dayOptions
        .map((day) => day.dayIndex)
        .filter((dayIndex) => !workingDayIndexes.value.includes(dayIndex));
});

const workingDaysLabel = computed(() => selectedGroupWorkingDays.value
    .map((day) => dayOptions.find((option) => option.value === String(day).toLowerCase())?.labelKey)
    .filter(Boolean)
    .map((labelKey) => t(labelKey))
    .join('، '));

const dateValue = computed({
    get: () => parseYmdDate(selectedDate.value),
    set: (value) => {
        selectedDate.value = formatYmdDate(value);
    },
});

const isAllowedDate = (value) => (
    !(value instanceof Date)
    || Number.isNaN(value.getTime())
    || disabledWeekDays.value.length === 0
    || !disabledWeekDays.value.includes(value.getDay())
);

const ensureAllowedDate = () => {
    const currentDate = dateValue.value ?? new Date();
    if (isAllowedDate(currentDate)) {
        if (!dateValue.value) {
            dateValue.value = currentDate;
        }
        return;
    }

    const cursor = new Date(currentDate);
    for (let index = 0; index < 14; index += 1) {
        cursor.setDate(cursor.getDate() + (index === 0 ? 0 : 1));
        if (isAllowedDate(cursor)) {
            dateValue.value = cursor;
            return;
        }
    }
};

const onCenterChange = async () => {
    selectedGroupId.value = groupOptions.value.length === 1 ? groupOptions.value[0].id : null;
    await nextTick();
    ensureAllowedDate();
};

const onGroupChange = async () => {
    await nextTick();
    ensureAllowedDate();
};

const selectionComplete = computed(() => (
    normalizeId(selectedCenterId.value) !== null
    && normalizeId(selectedGroupId.value) !== null
    && selectedDate.value !== ''
    && workingScheduleConfigured.value
));

const selectionDirty = computed(() => (
    normalizeId(selectedCenterId.value) !== normalizeId(props.selection.center_id)
    || normalizeId(selectedGroupId.value) !== normalizeId(props.selection.group_id)
    || selectedDate.value !== (props.selection.date ?? '')
));

const workspaceReady = computed(() => (
    normalizeId(props.selection.group_id) !== null
    && Boolean(props.selection.date)
    && !selectionDirty.value
));

const evaluationExists = computed(() => normalizeId(props.evaluation.id) !== null);
const homeworkExists = computed(() => normalizeId(props.homework.id) !== null);
const canAccessEvaluationSection = computed(() => (
    Boolean(props.permissions.can_view_evaluations)
    || Boolean(props.permissions.can_create_evaluation)
    || Boolean(props.permissions.can_update_evaluation)
));
const canAccessHomeworkSection = computed(() => (
    Boolean(props.permissions.can_view_homeworks)
    || Boolean(props.permissions.can_create_homework)
    || Boolean(props.permissions.can_update_homework)
));
const availableSections = computed(() => Number(canAccessEvaluationSection.value) + Number(canAccessHomeworkSection.value));
const completedSections = computed(() => (
    Number(canAccessEvaluationSection.value && evaluationExists.value)
    + Number(canAccessHomeworkSection.value && homeworkExists.value)
));
const allCompleted = computed(() => (
    availableSections.value > 0 && completedSections.value === availableSections.value
));
const canManageEvaluation = computed(() => (
    props.evaluation.accessible !== false && (evaluationExists.value
        ? Boolean(props.permissions.can_update_evaluation)
        : Boolean(props.permissions.can_create_evaluation))
));
const canManageHomework = computed(() => (
    props.homework.accessible !== false && (homeworkExists.value
        ? Boolean(props.permissions.can_update_homework)
        : Boolean(props.permissions.can_create_homework))
));
const selectedCenterName = computed(() => props.centers.find(
    (center) => normalizeId(center?.id) === normalizeId(props.selection.center_id),
)?.name ?? '');
const selectedGroupName = computed(() => groupOptions.value.find(
    (group) => normalizeId(group?.id) === normalizeId(props.selection.group_id),
)?.name ?? '');

const mapEvaluationStudents = (rows = []) => rows.map((row) => {
    const normalizeScore = (value) => {
        const parsed = Number(value ?? 10);

        return Number.isNaN(parsed) ? 10 : Math.min(10, Math.max(0, parsed));
    };
    const attendance = Number(row.attendances ?? 1);
    const normalizedAttendance = [1, 2, 3, 5, 6].includes(attendance) ? attendance : 1;
    const hasScores = [1, 6].includes(normalizedAttendance);
    const normalizedRow = {
        student_id: Number(row.student_id),
        full_name: row.full_name ?? '',
        plan_name: row.plan_name ?? null,
        group_name: row.group_name ?? null,
        alhifz: hasScores ? normalizeScore(row.alhifz) : null,
        warud: hasScores ? normalizeScore(row.warud) : null,
        akhlaqi: hasScores ? normalizeScore(row.akhlaqi) : null,
        tajwid: hasScores ? normalizeScore(row.tajwid) : null,
        note: row.note ?? '',
        attendances: normalizedAttendance,
        was_edited: Boolean(row.was_edited ?? false),
    };
    const normalizedNote = String(normalizedRow.note).trim();
    normalizedRow.is_default_entry = normalizedAttendance === 1
        && normalizedRow.alhifz === 10
        && normalizedRow.warud === 10
        && normalizedRow.akhlaqi === 10
        && normalizedRow.tajwid === 10
        && normalizedNote === '';

    return {
        ...normalizedRow,
        _baseline: {
            attendances: normalizedRow.attendances,
            alhifz: normalizedRow.alhifz,
            warud: normalizedRow.warud,
            akhlaqi: normalizedRow.akhlaqi,
            tajwid: normalizedRow.tajwid,
            note: normalizedRow.note,
        },
    };
});

const initialTrackingMap = new Map((props.plan_context.students ?? []).map(
    (tracking) => [Number(tracking.student_id), tracking],
));
const mapHomeworkStudents = (rows = []) => rows.map((row) => {
    const points = (row.points ?? []).map((point) => ({
        id: point.id ?? null,
        plan_point_id: Number(point.plan_point_id),
        name: point.name ?? '',
        points: Number(point.points ?? 0),
        is_done: Boolean(point.is_done ?? false),
        is_next_homework: Boolean(point.is_next_homework ?? false),
        is_previous_next_homework: Boolean(point.is_previous_next_homework ?? false),
        previous_next_homework_date: point.previous_next_homework_date ?? null,
        previous_next_homework_date_formatted: point.previous_next_homework_date_formatted ?? null,
        is_locked: Boolean(point.is_locked ?? false),
    }));
    const existingPointIds = new Set(points.map((point) => Number(point.plan_point_id)));
    const tracking = initialTrackingMap.get(Number(row.student_id));
    const plannedPoints = [
        ...(tracking?.today?.items ?? []).filter((point) => !point.completed),
        ...(tracking?.next?.items ?? []),
    ];

    plannedPoints.forEach((point) => {
        const pointId = Number(point.plan_point_id);
        if (!pointId || existingPointIds.has(pointId)) return;

        points.push({
            id: null,
            plan_point_id: pointId,
            name: point.name ?? '',
            points: Number(point.points ?? 0),
            is_done: false,
            is_next_homework: false,
            is_previous_next_homework: false,
            previous_next_homework_date: null,
            previous_next_homework_date_formatted: null,
            is_locked: false,
        });
        existingPointIds.add(pointId);
    });

    return {
        student_id: Number(row.student_id),
        full_name: row.full_name ?? '',
        plan_id: row.plan_id ?? null,
        plan_name: row.plan_name ?? null,
        group_name: row.group_name ?? null,
        points_balance: Number(row.points_balance ?? 0),
        points_balance_before: Number(row.points_balance_before ?? 0),
        points_adjustment: Number(row.points_adjustment ?? 0),
        points_adjustment_original: Number(row.points_adjustment_original ?? row.points_adjustment ?? 0),
        points_balance_after: Number(row.points_balance_after ?? 0),
        current_plan_point_name: row.current_plan_point_name ?? null,
        points,
    };
});

const evaluationForm = useForm({
    center_id: props.selection.center_id,
    group_id: props.selection.group_id,
    date: props.selection.date,
    evaluation_type: Number(props.evaluation.evaluation_type) === 2 ? 2 : 1,
    items: mapEvaluationStudents(props.evaluation.students),
});

const homeworkForm = useForm({
    center_id: props.selection.center_id,
    group_id: props.selection.group_id,
    date: props.selection.date,
    items: mapHomeworkStudents(props.homework.students),
});

const saveForm = useForm({});
const contextErrors = computed(() => ({
    ...evaluationForm.errors,
    ...homeworkForm.errors,
    ...saveForm.errors,
}));
const hasUnsavedChanges = computed(() => evaluationForm.isDirty || homeworkForm.isDirty);
const hasExistingFollowUp = computed(() => evaluationExists.value || homeworkExists.value);
const canUseWorkspace = computed(() => Boolean(props.plan_context.available) || hasExistingFollowUp.value);
const openStudentReport = ({ row, tracking }) => {
    if (!tracking?.report_url) return;
    reportStudent.value = { id: row.studentId, name: row.fullName };
    reportUrl.value = tracking.report_url;
    reportVisible.value = true;
};
const openTrackingReport = (tracking) => {
    if (!tracking?.report_url) return;
    reportStudent.value = { id: tracking.student_id, name: tracking.student_name };
    reportUrl.value = tracking.report_url;
    reportVisible.value = true;
};
const confirmDiscardChanges = () => (
    !hasUnsavedChanges.value
    || typeof window === 'undefined'
    || window.confirm(t('dailyFollowUp.unsavedChangesConfirm'))
);

const loadWorkspace = () => {
    if (!selectionComplete.value) {
        return;
    }

    if (!confirmDiscardChanges()) {
        return;
    }

    router.get('/admin/daily-follow-up', {
        center_id: selectedCenterId.value,
        group_id: selectedGroupId.value,
        date: selectedDate.value,
        evaluation_type: evaluationForm.evaluation_type,
    }, {
        preserveState: false,
        replace: true,
        onStart: () => {
            loadingWorkspace.value = true;
        },
        onFinish: () => {
            loadingWorkspace.value = false;
        },
    });
};

const submitFollowUp = () => {
    const evaluation = canManageEvaluation.value
        && evaluationForm.items.length > 0
        && (!evaluationExists.value || evaluationForm.isDirty)
        ? {
            evaluation_type: evaluationForm.evaluation_type,
            items: evaluationForm.items.map((item) => ({
                student_id: item.student_id,
                attendances: item.attendances,
                alhifz: item.alhifz,
                warud: item.warud,
                akhlaqi: item.akhlaqi,
                tajwid: item.tajwid,
                note: item.note,
            })),
        }
        : null;
    const homework = canManageHomework.value
        && homeworkForm.items.length > 0
        && (!homeworkExists.value || homeworkForm.isDirty)
        ? {
            items: homeworkForm.items.map((item) => ({
                student_id: item.student_id,
                points_adjustment: item.points_adjustment,
                points: (item.points ?? []).map((point) => ({
                    plan_point_id: point.plan_point_id,
                    is_done: point.is_done,
                    is_next_homework: point.is_next_homework,
                })),
            })),
        }
        : null;

    evaluationForm.clearErrors();
    homeworkForm.clearErrors();
    saveForm.clearErrors();
    saveForm.transform(() => ({
        center_id: props.selection.center_id,
        group_id: props.selection.group_id,
        date: props.selection.date,
        evaluation,
        homework,
    })).post('/admin/daily-follow-up/save', {
        preserveScroll: true,
        preserveState: 'errors',
        onError: (errors) => {
            const evaluationErrors = {};
            const homeworkErrors = {};

            Object.entries(errors).forEach(([key, message]) => {
                if (key.startsWith('evaluation.')) {
                    evaluationErrors[key.slice('evaluation.'.length)] = message;
                }
                if (key.startsWith('homework.')) {
                    homeworkErrors[key.slice('homework.'.length)] = message;
                }
            });

            evaluationForm.setError(evaluationErrors);
            homeworkForm.setError(homeworkErrors);
        },
    });
};
</script>

<template>
    <Head :title="t('dailyFollowUp.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('dailyFollowUp.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <header class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 shadow-(--shadow-sm) sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div class="max-w-3xl">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-[color-mix(in_oklab,var(--accent)_28%,var(--border))] bg-[color-mix(in_oklab,var(--accent)_9%,transparent)] px-3 py-1 text-xs font-semibold text-[var(--accent)]">
                            <i class="pi pi-list-check" aria-hidden="true"></i>
                            {{ t('dailyFollowUp.eyebrow') }}
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('dailyFollowUp.heading') }}</h2>
                        <p class="mt-3 text-base leading-7 text-(--muted-foreground)">{{ t('dailyFollowUp.description') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="permissions.can_view_evaluations"
                            href="/admin/evaluations"
                            @before="confirmDiscardChanges"
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-(--border) bg-(--background) px-3 text-sm font-medium transition-colors hover:bg-[color-mix(in_oklab,var(--accent)_8%,var(--background))]"
                        >
                            <i class="pi pi-clipboard" aria-hidden="true"></i>
                            {{ t('dailyFollowUp.evaluationsArchive') }}
                        </Link>
                        <Link
                            v-if="permissions.can_view_homeworks"
                            href="/admin/homeworks"
                            @before="confirmDiscardChanges"
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-(--border) bg-(--background) px-3 text-sm font-medium transition-colors hover:bg-[color-mix(in_oklab,var(--accent)_8%,var(--background))]"
                        >
                            <i class="pi pi-check-square" aria-hidden="true"></i>
                            {{ t('dailyFollowUp.homeworksArchive') }}
                        </Link>
                    </div>
                </div>
            </header>

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm) sm:p-6">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold">{{ t('dailyFollowUp.contextTitle') }}</h3>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('dailyFollowUp.contextDescription') }}</p>
                    </div>
                    <div
                        v-if="workspaceReady"
                        class="inline-flex items-center gap-2 rounded-full border border-(--border) bg-[color-mix(in_oklab,var(--foreground)_4%,var(--card))] px-3 py-1.5 text-sm font-medium"
                    >
                        <span>{{ completedSections }}/{{ availableSections }}</span>
                        <span>{{ t('dailyFollowUp.sectionsCompleted') }}</span>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto]">
                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="daily-follow-up-center"
                                v-model="selectedCenterId"
                                :options="centers"
                                option-label="name"
                                option-value="id"
                                filter
                                class="h-11 w-full"
                                @change="onCenterChange"
                            />
                            <FormFieldLabel for-id="daily-follow-up-center" :text="t('dailyFollowUp.center')" />
                        </FloatLabel>
                        <small v-if="contextErrors.center_id" class="mt-1 block text-sm text-red-600">
                            {{ contextErrors.center_id }}
                        </small>
                    </div>

                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="daily-follow-up-group"
                                v-model="selectedGroupId"
                                :options="groupOptions"
                                option-label="name"
                                option-value="id"
                                filter
                                class="h-11 w-full"
                                :disabled="!selectedCenterId"
                                @change="onGroupChange"
                            />
                            <FormFieldLabel for-id="daily-follow-up-group" :text="t('dailyFollowUp.group')" />
                        </FloatLabel>
                        <small v-if="contextErrors.group_id" class="mt-1 block text-sm text-red-600">
                            {{ contextErrors.group_id }}
                        </small>
                    </div>

                    <div>
                        <FloatLabel variant="on">
                            <DatePicker
                                input-id="daily-follow-up-date"
                                v-model="dateValue"
                                show-icon
                                icon-display="input"
                                date-format="yy-mm-dd"
                                :manual-input="false"
                                :disabled-days="disabledWeekDays"
                                class="h-11 w-full"
                            />
                            <FormFieldLabel for-id="daily-follow-up-date" :text="t('dailyFollowUp.date')" />
                        </FloatLabel>
                        <small v-if="workingDaysLabel" class="mt-1 block text-xs text-(--muted-foreground)">
                            {{ t('dailyFollowUp.workingDaysHint', { days: workingDaysLabel }) }}
                        </small>
                        <small v-else-if="selectedGroup" class="mt-1 block text-sm text-red-600">
                            {{ t('dailyFollowUp.workingDaysMissing') }}
                        </small>
                        <small v-if="contextErrors.date" class="mt-1 block text-sm text-red-600">
                            {{ contextErrors.date }}
                        </small>
                    </div>

                    <Button
                        type="button"
                        icon="pi pi-refresh"
                        :label="workspaceReady ? t('dailyFollowUp.refresh') : t('dailyFollowUp.openWorkspace')"
                        :loading="loadingWorkspace"
                        :disabled="!selectionComplete"
                        class="h-11 lg:min-w-40"
                        @click="loadWorkspace"
                    />
                </div>

                <p
                    v-if="date_adjustment"
                    class="mt-4 rounded-md border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-100"
                >
                    <i class="pi pi-calendar me-2" aria-hidden="true"></i>
                    {{ t('dailyFollowUp.dateAdjusted', { from: date_adjustment.from, to: date_adjustment.to }) }}
                </p>

                <p
                    v-if="selectionDirty && normalizeId(props.selection.group_id) !== null"
                    class="mt-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <i class="pi pi-info-circle me-2" aria-hidden="true"></i>
                    {{ t('dailyFollowUp.reloadHint') }}
                </p>
            </article>

            <article
                v-if="allCompleted && workspaceReady && !hasUnsavedChanges"
                class="flex flex-wrap items-center justify-between gap-4 rounded-(--radius-base) border border-[color-mix(in_oklab,var(--status-success)_42%,var(--border))] bg-[color-mix(in_oklab,var(--status-success)_10%,var(--card))] p-5 text-(--foreground)"
            >
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-full bg-[var(--status-success)] text-[var(--status-success-contrast)] shadow-(--shadow-sm)">
                        <i class="pi pi-check" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="font-semibold">{{ t('dailyFollowUp.completeTitle') }}</h3>
                        <p class="mt-1 text-sm opacity-80">{{ t('dailyFollowUp.completeDescription') }}</p>
                    </div>
                </div>
                <span class="rounded-full border border-[color-mix(in_oklab,var(--status-success)_32%,var(--border))] bg-[color-mix(in_oklab,var(--status-success)_5%,var(--card))] px-3 py-1.5 text-sm font-medium text-(--foreground)">
                    {{ selectedCenterName }} / {{ selectedGroupName }} / {{ selection.date }}
                </span>
            </article>

            <template v-if="workspaceReady">
                <article
                    v-if="plan_context.available"
                    class="overflow-hidden rounded-(--radius-base) border border-[color-mix(in_oklab,var(--accent)_24%,var(--border))] bg-(--card) shadow-(--shadow-sm)"
                >
                    <div class="grid gap-5 bg-[linear-gradient(130deg,color-mix(in_oklab,var(--accent)_10%,var(--card)),var(--card)_60%)] p-5 lg:grid-cols-[1fr_auto] lg:items-center sm:p-6">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] px-2.5 py-1 text-xs font-semibold text-[var(--accent)]">
                                    {{ t('dailyFollowUp.groupMonthlyPlan') }} #{{ plan_context.monthly_plan.id }}
                                </span>
                                <span class="text-xs text-(--muted-foreground)">{{ plan_context.monthly_plan.period_label }}</span>
                            </div>
                            <h3 class="mt-3 text-lg font-bold">{{ t('dailyFollowUp.planTrackingTitle') }}</h3>
                            <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">{{ t('dailyFollowUp.planTrackingDescription') }}</p>
                        </div>
                        <Link
                            v-if="permissions.can_view_monthly_plans"
                            :href="plan_context.monthly_plan.edit_url"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-(--border) bg-(--background) px-4 text-sm font-semibold transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
                        >
                            <i class="pi pi-map" aria-hidden="true"></i>
                            {{ t('dailyFollowUp.openMonthlyPlan') }}
                        </Link>
                    </div>
                    <div v-if="plan_context.progress_available" class="grid grid-cols-2 divide-x divide-(--border) border-t border-(--border) sm:grid-cols-5 rtl:divide-x-reverse">
                        <div v-for="item in [
                            ['on_track_count', 'on_track'],
                            ['ahead_count', 'ahead'],
                            ['behind_count', 'behind'],
                            ['not_due_count', 'not_due'],
                            ['missing_count', 'missing_student_plan'],
                        ]" :key="item[0]" class="px-3 py-3 text-center">
                            <strong class="block text-xl">{{ plan_context.summary?.[item[0]] ?? 0 }}</strong>
                            <span class="mt-1 block text-xs text-(--muted-foreground)">{{ t(`dailyFollowUp.planStatuses.${item[1]}`) }}</span>
                        </div>
                    </div>
                </article>

                <article
                    v-else
                    class="flex flex-wrap items-center justify-between gap-4 rounded-(--radius-base) border border-[color-mix(in_oklab,var(--status-warning)_42%,var(--border))] bg-[color-mix(in_oklab,var(--status-warning)_10%,var(--card))] p-5 text-(--foreground) shadow-(--shadow-sm) sm:p-6"
                >
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-[color-mix(in_oklab,var(--status-warning)_18%,var(--card))] text-[var(--status-warning)] ring-1 ring-inset ring-[color-mix(in_oklab,var(--status-warning)_28%,transparent)]">
                            <i class="pi pi-map" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h3 class="font-bold">{{ t('dailyFollowUp.missingMonthlyPlanTitle') }}</h3>
                            <p class="mt-1 max-w-2xl text-sm leading-6 opacity-85">{{ t('dailyFollowUp.missingMonthlyPlanDescription') }}</p>
                            <p v-if="hasExistingFollowUp" class="mt-2 text-xs font-semibold">{{ t('dailyFollowUp.historicalPlanException') }}</p>
                        </div>
                    </div>
                    <Link
                        v-if="permissions.can_create_monthly_plans && plan_context.create_url"
                        :href="plan_context.create_url"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-[var(--status-warning)] px-4 text-sm font-semibold text-[var(--status-warning-contrast)] shadow-(--shadow-sm) transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color-mix(in_oklab,var(--status-warning)_42%,transparent)]"
                    >
                        <i class="pi pi-plus" aria-hidden="true"></i>
                        {{ t('dailyFollowUp.createMonthlyPlan') }}
                    </Link>
                </article>

                <DailyFollowUpStudentTable
                    v-if="(canManageEvaluation || canManageHomework) && canUseWorkspace"
                    :evaluation-form="evaluationForm"
                    :homework-form="homeworkForm"
                    :evaluation-exists="evaluationExists"
                    :homework-exists="homeworkExists"
                    :can-manage-evaluation="canManageEvaluation"
                    :can-manage-homework="canManageHomework"
                    :can-view-reports="Boolean(permissions.can_view_reports)"
                    :errors="saveForm.errors"
                    :processing="saveForm.processing"
                    :plan-tracking="plan_context.students"
                    :plan-available="Boolean(plan_context.available)"
                    :plan-progress-available="Boolean(plan_context.progress_available)"
                    @submit="submitFollowUp"
                    @open-report="openStudentReport"
                />

                <article
                    v-else-if="permissions.can_view_reports && plan_context.available"
                    class="overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--card) shadow-(--shadow-sm)"
                >
                    <header class="border-b border-(--border) bg-[color-mix(in_oklab,var(--foreground)_3.5%,var(--card))] p-5">
                        <h3 class="font-semibold">{{ t('dailyFollowUp.readOnlyReportsTitle') }}</h3>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('dailyFollowUp.readOnlyReportsDescription') }}</p>
                    </header>
                    <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <button
                            v-for="tracking in plan_context.students.filter((item) => item.report_url)"
                            :key="tracking.student_id"
                            type="button"
                            class="group flex items-center gap-3 rounded-xl border border-(--border) bg-(--background) p-4 text-start transition hover:border-[var(--accent)] hover:shadow-(--shadow-sm)"
                            @click="openTrackingReport(tracking)"
                        >
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[color-mix(in_oklab,var(--accent)_10%,transparent)] text-[var(--accent)]">
                                <i class="pi pi-chart-line" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <strong class="block truncate text-sm">{{ tracking.student_name }}</strong>
                                <span class="mt-1 block text-xs text-(--muted-foreground)">{{ t(`dailyFollowUp.planStatuses.${tracking.status}`) }}</span>
                            </span>
                            <strong class="text-sm text-[var(--accent)]">{{ tracking.progress_percentage ?? '—' }}%</strong>
                        </button>
                    </div>
                </article>

                <article v-else-if="!canManageEvaluation && !canManageHomework" class="rounded-(--radius-base) border border-(--border) bg-(--card) p-8 text-center shadow-(--shadow-sm)">
                    <span class="mx-auto grid size-12 place-items-center rounded-full bg-[color-mix(in_oklab,var(--foreground)_5%,var(--card))] text-(--muted-foreground)">
                        <i class="pi pi-lock text-xl" aria-hidden="true"></i>
                    </span>
                    <h3 class="mt-4 font-semibold">{{ t('dailyFollowUp.readOnlyTitle') }}</h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-(--muted-foreground)">{{ t('dailyFollowUp.readOnlyUnifiedDescription') }}</p>
                </article>
            </template>

            <article v-else-if="!selectionDirty" class="rounded-(--radius-base) border border-dashed border-(--border) bg-(--card) px-6 py-12 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-[color-mix(in_oklab,var(--foreground)_5%,var(--card))] text-(--muted-foreground)">
                    <i class="pi pi-calendar-plus text-xl" aria-hidden="true"></i>
                </span>
                <h3 class="mt-4 text-lg font-semibold">{{ t('dailyFollowUp.emptyTitle') }}</h3>
                <p class="mx-auto mt-2 max-w-xl text-sm text-(--muted-foreground)">{{ t('dailyFollowUp.emptyDescription') }}</p>
            </article>

            <StudentFollowUpReportDialog
                v-model="reportVisible"
                :student="reportStudent"
                :report-url="reportUrl"
                :has-unsaved-changes="hasUnsavedChanges"
            />
        </section>
    </AdminLayout>
</template>
