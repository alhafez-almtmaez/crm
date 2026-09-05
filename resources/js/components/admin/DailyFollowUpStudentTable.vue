<script setup>
import Button from 'primevue/button';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    planStatusBadgeClass,
    planStatusProgressClass,
    planStatusTextClass,
} from '../../admin/planStatusStyles';

const props = defineProps({
    evaluationForm: { type: Object, required: true },
    homeworkForm: { type: Object, required: true },
    evaluationExists: { type: Boolean, default: false },
    homeworkExists: { type: Boolean, default: false },
    canManageEvaluation: { type: Boolean, default: false },
    canManageHomework: { type: Boolean, default: false },
    canViewReports: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
    planTracking: { type: Array, default: () => [] },
    planAvailable: { type: Boolean, default: false },
    planProgressAvailable: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'open-report']);
const { t } = useI18n();
const search = ref('');
const attendanceHasScores = (value) => [1, 6].includes(Number(value));

const attendanceOptions = computed(() => [
    { value: 1, label: t('evaluations.present') },
    { value: 6, label: t('evaluations.late') },
    { value: 2, label: t('evaluations.excusedAbsence') },
    { value: 3, label: t('evaluations.absence') },
    { value: 5, label: t('evaluations.exempt') },
]);
const scoreModeOptions = computed(() => [
    { value: 1, label: t('evaluations.alhifz') },
    { value: 2, label: t('evaluations.tajwid') },
]);
const scoreMode = computed({
    get: () => (Number(props.evaluationForm.evaluation_type) === 2 ? 2 : 1),
    set: (value) => {
        props.evaluationForm.evaluation_type = Number(value) === 2 ? 2 : 1;
    },
});
const primaryScoreField = computed(() => (scoreMode.value === 2 ? 'tajwid' : 'alhifz'));
const primaryScoreLabel = computed(() => (
    scoreMode.value === 2 ? t('evaluations.tajwid') : t('evaluations.alhifz')
));
const planTrackingMap = computed(() => new Map((props.planTracking ?? []).map(
    (tracking) => [Number(tracking.student_id), tracking],
)));

const studentRows = computed(() => {
    const rows = new Map();

    (props.evaluationForm.items ?? []).forEach((item, index) => {
        rows.set(Number(item.student_id), {
            studentId: Number(item.student_id),
            fullName: item.full_name ?? '',
            planName: item.plan_name ?? null,
            groupName: item.group_name ?? null,
            evaluationItem: item,
            evaluationIndex: index,
            homeworkItem: null,
            homeworkIndex: null,
        });
    });

    (props.homeworkForm.items ?? []).forEach((item, index) => {
        const studentId = Number(item.student_id);
        const row = rows.get(studentId) ?? {
            studentId,
            fullName: '',
            planName: null,
            groupName: null,
            evaluationItem: null,
            evaluationIndex: null,
        };

        rows.set(studentId, {
            ...row,
            fullName: row.fullName || item.full_name || '',
            planName: row.planName || item.plan_name || null,
            groupName: row.groupName || item.group_name || null,
            homeworkItem: item,
            homeworkIndex: index,
        });
    });

    return [...rows.values()]
        .map((row) => ({ ...row, tracking: planTrackingMap.value.get(row.studentId) ?? null }))
        .sort((first, second) => String(first.fullName).localeCompare(String(second.fullName), 'ar'));
});

const filteredRows = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase();
    if (!needle) {
        return studentRows.value;
    }

    return studentRows.value.filter((row) => [row.fullName, row.planName, row.groupName]
        .some((value) => String(value ?? '').toLocaleLowerCase().includes(needle)));
});
const evaluationWillSave = computed(() => (
    props.canManageEvaluation
    && (props.evaluationForm.items?.length ?? 0) > 0
    && (!props.evaluationExists || props.evaluationForm.isDirty)
));
const homeworkWillSave = computed(() => (
    props.canManageHomework
    && (props.homeworkForm.items?.length ?? 0) > 0
    && (!props.homeworkExists || props.homeworkForm.isDirty)
));
const canSubmit = computed(() => evaluationWillSave.value || homeworkWillSave.value);
const errorCount = computed(() => Object.keys(props.errors ?? {}).length);
const errorForPrefix = (prefix) => Object.entries(props.errors ?? {})
    .find(([path]) => path === prefix || path.startsWith(`${prefix}.`))?.[1] ?? null;

const clampScore = (item, field) => {
    if (item[field] === null || item[field] === '') {
        item[field] = null;
        return;
    }

    const value = Number(item[field]);
    item[field] = Number.isNaN(value) ? null : Math.min(10, Math.max(0, value));
};
const onAttendanceChange = (item) => {
    ['alhifz', 'warud', 'akhlaqi', 'tajwid'].forEach((field) => {
        if (!attendanceHasScores(item.attendances)) {
            item[field] = null;
        } else if (item[field] === null || item[field] === '') {
            item[field] = 10;
        }
    });
};
const plannedPointIds = (tracking, section) => new Set((tracking?.[section]?.items ?? []).map(
    (point) => Number(point.plan_point_id),
));
const pointOptions = (item, tracking) => {
    const todayIds = plannedPointIds(tracking, 'today');
    const nextIds = plannedPointIds(tracking, 'next');

    return (item?.points ?? []).map((point) => ({
        label: [
            todayIds.has(Number(point.plan_point_id)) ? t('dailyFollowUp.plannedToday') : null,
            nextIds.has(Number(point.plan_point_id)) ? t('dailyFollowUp.plannedNext') : null,
            point.name,
            t('homeworks.pointValue', { points: Number(point.points ?? 0) }),
            point.is_locked ? t('homeworks.awarded') : null,
            point.is_previous_next_homework
                ? [t('homeworks.previousNextHomework'), point.previous_next_homework_date_formatted]
                    .filter(Boolean)
                    .join(' ')
                : null,
        ].filter(Boolean).join(' · '),
        value: Number(point.plan_point_id),
        disabled: Boolean(point.is_locked),
    }));
};
const selectedPointIds = (item, field) => (item?.points ?? [])
    .filter((point) => point[field])
    .map((point) => Number(point.plan_point_id));
const updatePoints = (item, field, selectedIds = []) => {
    const selected = new Set(selectedIds.map(Number));
    const otherField = field === 'is_done' ? 'is_next_homework' : 'is_done';

    (item?.points ?? []).forEach((point) => {
        if (point.is_locked) {
            point.is_done = true;
            point.is_next_homework = false;
            return;
        }
        point[field] = selected.has(Number(point.plan_point_id));
        if (point[field]) {
            point[otherField] = false;
        }
    });
};
const trackingPercentage = (tracking) => tracking?.progress_percentage ?? 0;
const planItemsLabel = (items = []) => items.map((item) => item.name).filter(Boolean).join('، ');

watch(errorCount, (count) => {
    if (count > 0) search.value = '';
});
</script>

<template>
    <article class="overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--card) text-(--card-foreground) shadow-(--shadow-sm)">
        <header class="border-b border-(--border) bg-[color-mix(in_oklab,var(--foreground)_3.5%,var(--card))] p-4 sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h3 class="text-lg font-semibold">{{ t('dailyFollowUp.unifiedTitle') }}</h3>
                    <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('dailyFollowUp.unifiedDescription') }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:w-[34rem]">
                    <label class="grid gap-1.5 text-xs font-semibold text-(--muted-foreground)">
                        <span>{{ t('evaluations.scoreFieldMode') }}</span>
                        <Select
                            v-model="scoreMode"
                            :options="scoreModeOptions"
                            option-label="label"
                            option-value="value"
                            class="h-10 w-full bg-(--background)"
                            :disabled="evaluationExists || !canManageEvaluation"
                        />
                    </label>
                    <label class="grid gap-1.5 text-xs font-semibold text-(--muted-foreground)">
                        <span>{{ t('dailyFollowUp.searchStudents') }}</span>
                        <input
                            v-model="search"
                            type="search"
                            class="h-10 w-full rounded-md border border-(--border) bg-(--background) px-3 text-sm text-(--foreground) outline-none transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--accent)_16%,transparent)]"
                            :placeholder="t('dailyFollowUp.searchStudentsPlaceholder')"
                        />
                    </label>
                </div>
            </div>
        </header>

        <div v-if="errorCount" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" role="alert">
            {{ errors.follow_up || t('dailyFollowUp.validationErrors', { count: errorCount }) }}
        </div>
        <div v-if="filteredRows.length === 0" class="px-5 py-12 text-center text-sm text-(--muted-foreground)">
            {{ studentRows.length ? t('dailyFollowUp.noMatchingStudents') : t('dailyFollowUp.noStudents') }}
        </div>

        <div v-else class="divide-y divide-(--border)">
            <section
                v-for="(row, displayIndex) in filteredRows"
                :key="row.studentId"
                class="grid gap-3 bg-(--card) p-4 transition-colors hover:bg-[color-mix(in_oklab,var(--foreground)_2.5%,var(--card))] xl:grid-cols-[minmax(15rem,0.85fr)_minmax(20rem,1.15fr)_minmax(21rem,1.3fr)] xl:items-start"
            >
                <div class="min-w-0 rounded-lg border border-(--border) bg-[color-mix(in_oklab,var(--foreground)_2.5%,var(--card))] p-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[color-mix(in_oklab,var(--accent)_25%,var(--border))] bg-[color-mix(in_oklab,var(--accent)_10%,transparent)] text-sm font-bold text-[var(--accent)]">
                            {{ displayIndex + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ row.fullName }}</p>
                            <p class="mt-1 truncate text-xs text-(--muted-foreground)">{{ row.tracking?.plan_name || row.planName || t('common.na') }}</p>
                        </div>
                        <Button
                            v-if="canViewReports && row.tracking?.report_url"
                            type="button"
                            icon="pi pi-chart-line"
                            :label="t('dailyFollowUp.reportButton')"
                            severity="secondary"
                            outlined
                            size="small"
                            :aria-label="t('dailyFollowUp.openStudentReport', { student: row.fullName })"
                            @click="emit('open-report', { row, tracking: row.tracking })"
                        />
                    </div>

                    <template v-if="row.tracking">
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold shadow-sm" :class="planStatusBadgeClass(row.tracking.status)">
                                {{ t(`dailyFollowUp.planStatuses.${row.tracking.status}`) }}
                            </span>
                            <span class="text-xs font-bold" :class="planStatusTextClass(row.tracking.status)">
                                {{ row.tracking.progress_percentage ?? '—' }}%
                            </span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-[color-mix(in_oklab,var(--foreground)_8%,transparent)]">
                            <span
                                class="block h-full rounded-full transition-[width] duration-500"
                                :class="planStatusProgressClass(row.tracking.status)"
                                :style="{ width: `${Math.min(100, Math.max(0, trackingPercentage(row.tracking)))}%` }"
                            ></span>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-(--muted-foreground)">
                            <span>{{ t('dailyFollowUp.overallProgress') }}</span>
                            <span>{{ row.tracking.completed_items_count }}/{{ row.tracking.total_items_count }}</span>
                        </div>
                        <p v-if="row.tracking.due_items_count" class="mt-1 text-[11px] text-(--muted-foreground)">
                            {{ t('dailyFollowUp.dueProgress', { completed: row.tracking.due_completed_items_count, due: row.tracking.due_items_count, percentage: row.tracking.adherence_percentage ?? 0 }) }}
                        </p>
                    </template>
                    <p v-else-if="planAvailable && planProgressAvailable" class="mt-3 rounded-md border border-[color-mix(in_oklab,var(--status-warning)_55%,var(--border))] bg-[color-mix(in_oklab,var(--status-warning)_18%,var(--card))] px-2 py-1.5 text-xs font-medium text-(--foreground)">
                        {{ t('dailyFollowUp.planStatuses.missing_student_plan') }}
                    </p>
                    <p v-else-if="!planAvailable" class="mt-3 text-xs text-(--muted-foreground)">{{ t('dailyFollowUp.planUnavailableForHistory') }}</p>
                </div>

                <div class="rounded-lg border border-(--border) bg-(--background) p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h4 class="flex items-center gap-2 text-sm font-semibold">
                            <i class="pi pi-clipboard text-[var(--accent)]" aria-hidden="true"></i>
                            {{ t('dailyFollowUp.evaluationTab') }}
                        </h4>
                        <span v-if="evaluationExists && !evaluationForm.isDirty" class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            {{ t('dailyFollowUp.saved') }}
                        </span>
                    </div>
                    <div v-if="row.evaluationItem" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="grid gap-1 text-xs font-medium text-(--muted-foreground)">
                            <span>{{ t('evaluations.attendance') }}</span>
                            <Select
                                v-model.number="row.evaluationItem.attendances"
                                :options="attendanceOptions"
                                option-label="label"
                                option-value="value"
                                class="h-10 w-full"
                                :disabled="!canManageEvaluation"
                                @update:model-value="onAttendanceChange(row.evaluationItem)"
                            />
                        </label>
                        <label
                            v-for="field in [primaryScoreField, 'warud', 'akhlaqi']"
                            :key="field"
                            class="grid gap-1 text-xs font-medium text-(--muted-foreground)"
                        >
                            <span>{{ field === primaryScoreField ? primaryScoreLabel : t(`evaluations.${field}`) }}</span>
                            <input
                                v-model.number="row.evaluationItem[field]"
                                type="number"
                                min="0"
                                max="10"
                                class="h-10 w-full rounded-md border border-(--border) bg-(--card) px-2 text-(--foreground) outline-none focus:border-[var(--accent)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--accent)_16%,transparent)] disabled:opacity-55"
                                :disabled="!canManageEvaluation || !attendanceHasScores(row.evaluationItem.attendances)"
                                @input="clampScore(row.evaluationItem, field)"
                            />
                        </label>
                        <label class="grid gap-1 text-xs font-medium text-(--muted-foreground) sm:col-span-2 lg:col-span-4">
                            <span>{{ t('evaluations.note') }}</span>
                            <input
                                v-model="row.evaluationItem.note"
                                type="text"
                                class="h-10 w-full rounded-md border border-(--border) bg-(--card) px-3 text-sm text-(--foreground) outline-none focus:border-[var(--accent)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--accent)_16%,transparent)] disabled:opacity-55"
                                :disabled="!canManageEvaluation"
                            />
                        </label>
                        <small v-if="errorForPrefix(`evaluation.items.${row.evaluationIndex}`)" class="text-xs text-red-600 sm:col-span-2 lg:col-span-4">
                            {{ errorForPrefix(`evaluation.items.${row.evaluationIndex}`) }}
                        </small>
                    </div>
                    <p v-else class="py-4 text-center text-sm text-(--muted-foreground)">
                        {{ t('dailyFollowUp.evaluationNotApplicable') }}
                    </p>
                </div>

                <div class="rounded-lg border border-(--border) bg-(--background) p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h4 class="flex items-center gap-2 text-sm font-semibold">
                            <i class="pi pi-check-square text-[var(--accent)]" aria-hidden="true"></i>
                            {{ t('dailyFollowUp.homeworkTab') }}
                        </h4>
                        <span v-if="row.homeworkItem" class="text-xs text-(--muted-foreground)">
                            {{ t('homeworks.balance') }}:
                            <strong class="text-(--foreground)">{{ row.homeworkItem.points_balance ?? 0 }}</strong>
                        </span>
                    </div>
                    <div v-if="row.homeworkItem" class="grid gap-3 md:grid-cols-2">
                        <label class="grid min-w-0 gap-1 text-xs font-medium text-(--muted-foreground)">
                            <span>{{ t('dailyFollowUp.completedPlanPoints') }}</span>
                            <MultiSelect
                                :model-value="selectedPointIds(row.homeworkItem, 'is_done')"
                                :options="pointOptions(row.homeworkItem, row.tracking)"
                                option-label="label"
                                option-value="value"
                                option-disabled="disabled"
                                filter
                                :max-selected-labels="1"
                                :selected-items-label="t('common.selectedCount')"
                                class="min-h-10 w-full bg-(--card)"
                                :disabled="!canManageHomework || !row.homeworkItem.points?.length"
                                @update:model-value="updatePoints(row.homeworkItem, 'is_done', $event)"
                            />
                        </label>
                        <label class="grid min-w-0 gap-1 text-xs font-medium text-(--muted-foreground)">
                            <span>{{ t('dailyFollowUp.nextPlanPoints') }}</span>
                            <MultiSelect
                                :model-value="selectedPointIds(row.homeworkItem, 'is_next_homework')"
                                :options="pointOptions(row.homeworkItem, row.tracking)"
                                option-label="label"
                                option-value="value"
                                option-disabled="disabled"
                                filter
                                :max-selected-labels="1"
                                :selected-items-label="t('common.selectedCount')"
                                class="min-h-10 w-full bg-(--card)"
                                :disabled="!canManageHomework || !row.homeworkItem.points?.length"
                                @update:model-value="updatePoints(row.homeworkItem, 'is_next_homework', $event)"
                            />
                        </label>
                        <div v-if="row.tracking?.today?.items?.length" class="rounded-md border border-[color-mix(in_oklab,var(--accent)_24%,var(--border))] bg-[color-mix(in_oklab,var(--accent)_7%,var(--card))] px-3 py-2 text-xs md:col-span-2">
                            <strong class="text-[var(--accent)]">{{ t('dailyFollowUp.plannedToday') }}:</strong>
                            <span class="ms-1 text-(--foreground)">{{ planItemsLabel(row.tracking.today.items) }}</span>
                        </div>
                        <div v-if="row.tracking?.next?.items?.length" class="rounded-md border border-(--border) bg-(--card) px-3 py-2 text-xs md:col-span-2">
                            <strong>{{ t('dailyFollowUp.plannedNext') }}:</strong>
                            <span class="ms-1 text-(--muted-foreground)">{{ planItemsLabel(row.tracking.next.items) }}</span>
                            <span v-if="row.tracking.next.date" class="ms-1 text-(--muted-foreground)">({{ row.tracking.next.date }})</span>
                        </div>
                        <label class="grid gap-1 text-xs font-medium text-(--muted-foreground)">
                            <span>{{ t('homeworks.pointsAdjustment') }}</span>
                            <input
                                v-model.number="row.homeworkItem.points_adjustment"
                                type="number"
                                class="h-10 w-full rounded-md border border-(--border) bg-(--card) px-3 text-(--foreground) outline-none focus:border-[var(--accent)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--accent)_16%,transparent)] disabled:opacity-55"
                                :disabled="!canManageHomework"
                            />
                        </label>
                        <div class="rounded-md border border-(--border) bg-[color-mix(in_oklab,var(--foreground)_3.5%,var(--card))] px-3 py-2 text-xs text-(--muted-foreground)">
                            {{ t('homeworks.progress') }}:
                            <strong class="ms-1 text-(--foreground)">{{ row.homeworkItem.current_plan_point_name || t('homeworks.notStarted') }}</strong>
                        </div>
                        <small v-if="errorForPrefix(`homework.items.${row.homeworkIndex}`)" class="text-xs text-red-600 md:col-span-2">
                            {{ errorForPrefix(`homework.items.${row.homeworkIndex}`) }}
                        </small>
                    </div>
                    <p v-else class="py-4 text-center text-sm text-(--muted-foreground)">
                        {{ t('dailyFollowUp.homeworkNotApplicable') }}
                    </p>
                </div>
            </section>
        </div>

        <footer class="sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 border-t border-(--border) bg-(--card)/95 px-4 py-4 backdrop-blur sm:px-5">
            <p class="text-sm text-(--muted-foreground)">
                {{ t('dailyFollowUp.studentsCount', { count: filteredRows.length }) }}
            </p>
            <Button
                type="button"
                icon="pi pi-save"
                :label="t('dailyFollowUp.saveAll')"
                :loading="processing"
                :disabled="!canSubmit || processing"
                @click="emit('submit')"
            />
        </footer>
    </article>
</template>
