<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import { computed, ref, shallowRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppToast } from '../../composables/useAppToast';
import FormFieldLabel from '../form/FormFieldLabel.vue';

const props = defineProps({
    description: {
        type: String,
        default: '',
    },
    form: {
        type: Object,
        required: true,
    },
    centers: {
        type: Array,
        default: () => [],
    },
    lockCenterAndDate: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['cancel', 'reload', 'submit']);
const { t } = useI18n();
const appToast = useAppToast();
const historyVisible = ref(false);
const historyLoading = ref(false);
const historyRows = ref([]);
const historyStudent = ref(null);
const studentSearch = shallowRef('');
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

const dateValue = computed({
    get: () => {
        const value = props.form.date;
        if (typeof value !== 'string' || value === '') {
            return null;
        }

        const parts = value.split('-').map((segment) => Number(segment));
        if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
            return null;
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    },
    set: (value) => {
        if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
            props.form.date = '';
            return;
        }

        const year = value.getFullYear();
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');
        props.form.date = `${year}-${month}-${day}`;
    },
});

const selectedCenter = computed(() => props.centers.find((center) => Number(center.id) === Number(props.form.center_id)) ?? null);

const selectedCenterWorkingDays = computed(() => {
    const workingDays = selectedCenter.value?.working_days;

    return Array.isArray(workingDays) ? workingDays : [];
});

const workingDayIndexes = computed(() => selectedCenterWorkingDays.value
    .map((day) => dayIndexByName[String(day).toLowerCase()])
    .filter((dayIndex) => Number.isInteger(dayIndex)));

const disabledWeekDays = computed(() => {
    if (!selectedCenter.value || workingDayIndexes.value.length === 0) {
        return [];
    }

    return dayOptions
        .map((day) => day.dayIndex)
        .filter((dayIndex) => !workingDayIndexes.value.includes(dayIndex));
});

const workingDaysLabel = computed(() => {
    if (!selectedCenter.value || selectedCenterWorkingDays.value.length === 0) {
        return '';
    }

    return selectedCenterWorkingDays.value
        .map((day) => dayOptions.find((option) => option.value === String(day).toLowerCase())?.labelKey)
        .filter(Boolean)
        .map((labelKey) => t(labelKey))
        .join('، ');
});

const isAllowedDate = (value) => {
    if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
        return true;
    }

    return disabledWeekDays.value.length === 0 || !disabledWeekDays.value.includes(value.getDay());
};

const nextAllowedDate = (value) => {
    const cursor = value instanceof Date && !Number.isNaN(value.getTime())
        ? new Date(value)
        : new Date();

    for (let index = 0; index < 14; index += 1) {
        if (isAllowedDate(cursor)) {
            return cursor;
        }

        cursor.setDate(cursor.getDate() + 1);
    }

    return value;
};

watch(
    () => [props.form.center_id, props.form.date, disabledWeekDays.value.join(',')],
    () => {
        if (props.lockCenterAndDate || !props.form.center_id || disabledWeekDays.value.length === 0) {
            return;
        }

        const currentDate = dateValue.value ?? new Date();
        if (isAllowedDate(currentDate)) {
            return;
        }

        dateValue.value = nextAllowedDate(currentDate);
    },
    { immediate: true },
);

const totalDonePoints = computed(() => props.form.items.reduce((total, item) => (
    total + (item.points ?? []).reduce((sum, point) => (
        point.is_done ? sum + Number(point.points ?? 0) : sum
    ), 0)
), 0));

const totalManualAdjustments = computed(() => props.form.items.reduce((total, item) => (
    total + Number(item.points_adjustment ?? 0)
), 0));

const homeworkItemRows = computed(() => props.form.items.map((item, itemIndex) => ({ item, itemIndex })));
const normalizedStudentSearch = computed(() => studentSearch.value.trim().toLowerCase());

const filteredHomeworkItems = computed(() => {
    if (normalizedStudentSearch.value === '') {
        return homeworkItemRows.value;
    }

    return homeworkItemRows.value.filter(({ item }) => String(item.full_name ?? '')
        .toLowerCase()
        .includes(normalizedStudentSearch.value));
});

const expectedBalanceAfterAdjustment = (item) => (
    Number(item.points_balance ?? 0)
    + Number(item.points_adjustment ?? 0)
    - Number(item.points_adjustment_original ?? 0)
);

const shortPreviousHomeworkDate = (point) => {
    const value = point?.previous_next_homework_date;
    if (typeof value !== 'string' || value === '') {
        return point?.previous_next_homework_date_formatted ?? '';
    }

    const [year, month, day] = value.split('-').map((segment) => Number(segment));
    if (!year || !month || !day) {
        return point?.previous_next_homework_date_formatted ?? '';
    }

    return `${day}/${month}`;
};

const studentNameToneClass = (index) => {
    const accents = [
        'text-cyan-700 dark:text-cyan-300',
        'text-emerald-700 dark:text-emerald-300',
        'text-amber-700 dark:text-amber-300',
        'text-rose-700 dark:text-rose-300',
    ];

    return accents[index % accents.length];
};

const planToneClass = (index) => {
    const accents = [
        'border-cyan-200 text-cyan-700 dark:border-cyan-800 dark:text-cyan-300',
        'border-emerald-200 text-emerald-700 dark:border-emerald-800 dark:text-emerald-300',
        'border-amber-200 text-amber-700 dark:border-amber-800 dark:text-amber-300',
        'border-rose-200 text-rose-700 dark:border-rose-800 dark:text-rose-300',
    ];

    return accents[index % accents.length];
};

const adjustmentToneClass = (item) => {
    const value = Number(item.points_adjustment ?? 0);

    if (value > 0) {
        return 'border-emerald-300 text-emerald-800 dark:border-emerald-700 dark:text-emerald-200';
    }

    if (value < 0) {
        return 'border-rose-300 text-rose-800 dark:border-rose-700 dark:text-rose-200';
    }

    return 'border-(--border) text-(--muted-foreground)';
};

const updatePointsAdjustment = (item, event) => {
    const rawValue = event?.target?.value ?? '';
    const parsedValue = rawValue === '' ? 0 : Number(rawValue);
    item.points_adjustment = Number.isFinite(parsedValue) ? parsedValue : 0;
};

const openHistory = async (item) => {
    historyStudent.value = item;
    historyRows.value = [];
    historyVisible.value = true;
    historyLoading.value = true;

    try {
        const { data } = await axios.get(`/admin/homeworks/students/${item.student_id}/point-history`);
        historyRows.value = data?.data ?? [];
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('homeworks.historyFailed'),
        });
    } finally {
        historyLoading.value = false;
    }
};

const togglePoint = (point, value) => {
    if (point?.is_locked) {
        point.is_done = true;
        point.is_next_homework = false;
        return;
    }

    point.is_done = Boolean(value);
    if (point.is_done) {
        point.is_next_homework = false;
    }
};

const toggleNextHomework = (point, value) => {
    if (point?.is_locked) {
        point.is_next_homework = false;
        return;
    }

    point.is_next_homework = Boolean(value);
    if (point.is_next_homework) {
        point.is_done = false;
    }
};

const pointCardClass = (point) => {
    if (point?.is_done) {
        return [
            'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100',
            point?.is_locked ? 'cursor-default opacity-80' : '',
        ];
    }

    if (point?.is_previous_next_homework) {
        return [
            'border-amber-300 bg-amber-50/70 text-amber-950 dark:border-amber-800 dark:bg-amber-950/25 dark:text-amber-100',
            point?.is_locked ? 'cursor-default opacity-80' : 'hover:border-amber-500',
        ];
    }

    return [
        'hover:border-(--primary)',
        point?.is_locked ? 'cursor-default opacity-80' : '',
    ];
};
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="emit('submit')">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            input-id="homework-center"
                            v-model="form.center_id"
                            :options="centers"
                            option-label="name"
                            option-value="id"
                            filter
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            :disabled="lockCenterAndDate"
                        />
                        <FormFieldLabel for-id="homework-center" :text="t('homeworks.center')" />
                    </FloatLabel>
                    <small v-if="form.errors.center_id" class="text-sm text-red-600">{{ form.errors.center_id }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <DatePicker
                            input-id="homework-date"
                            v-model="dateValue"
                            show-icon
                            icon-display="input"
                            date-format="yy-mm-dd"
                            :manual-input="false"
                            :disabled-days="disabledWeekDays"
                            class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                            :disabled="lockCenterAndDate"
                        />
                        <FormFieldLabel for-id="homework-date" :text="t('homeworks.date')" />
                    </FloatLabel>
                    <small v-if="form.errors.date" class="text-sm text-red-600">{{ form.errors.date }}</small>
                    <small v-else-if="workingDaysLabel" class="text-xs text-(--muted-foreground)">
                        {{ t('homeworks.centerWorkingDaysHint', { days: workingDaysLabel }) }}
                    </small>
                </div>
            </div>

            <div v-if="!lockCenterAndDate" class="flex justify-end">
                <Button
                    type="button"
                    icon="pi pi-refresh"
                    :label="t('homeworks.loadStudents')"
                    severity="secondary"
                    :disabled="!form.center_id || !form.date"
                    @click="emit('reload')"
                />
            </div>

            <div class="rounded-md border border-(--border)">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-(--border) bg-(--muted)/35 px-4 py-3">
                    <div>
                        <h3 class="text-lg font-semibold">{{ t('homeworks.studentsList') }}</h3>
                        <p class="text-sm text-(--muted-foreground)">{{ t('homeworks.studentsHint') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="studentSearch"
                            type="search"
                            class="h-10 min-w-56 rounded-md border border-(--border) bg-(--background) px-3 text-sm text-(--foreground) outline-none transition-colors focus:border-(--primary)"
                            :aria-label="t('homeworks.searchStudent')"
                            :placeholder="t('homeworks.searchStudentPlaceholder')"
                        >
                        <div class="rounded-md border border-(--border) bg-(--background) px-3 py-2 text-sm font-medium">
                            {{ t('homeworks.studentsCount') }}: {{ filteredHomeworkItems.length }} / {{ form.items.length }}
                        </div>
                        <div class="rounded-md border border-(--border) bg-(--background) px-3 py-2 text-sm font-medium">
                            {{ t('homeworks.selectedPointsTotal', { points: totalDonePoints }) }}
                        </div>
                        <div class="rounded-md border border-(--border) bg-(--background) px-3 py-2 text-sm font-medium">
                            {{ t('homeworks.manualAdjustmentsTotal', { points: totalManualAdjustments }) }}
                        </div>
                    </div>
                </div>

                <div v-if="form.items.length === 0" class="px-4 py-6 text-sm text-(--muted-foreground)">
                    {{ t('homeworks.noStudentsLoaded') }}
                </div>

                <div v-else>
                    <div v-if="form.errors.items" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ form.errors.items }}
                    </div>

                    <div v-if="filteredHomeworkItems.length === 0" class="px-4 py-6 text-sm text-(--muted-foreground)">
                        {{ t('homeworks.noStudentSearchResults') }}
                    </div>

                    <div v-else class="divide-y divide-(--border)">
                        <section
                            v-for="({ item, itemIndex }, displayIndex) in filteredHomeworkItems"
                            :key="item.student_id"
                            class="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(15rem,18rem)_minmax(11rem,13rem)_1fr]"
                        >
                        <div class="min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold" :class="studentNameToneClass(displayIndex)">{{ item.full_name }}</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-(--muted-foreground)">
                                        <span
                                            class="inline-flex max-w-full items-center rounded-md border px-2 py-0.5 font-medium"
                                            :class="planToneClass(displayIndex)"
                                        >
                                            {{ item.plan_name || t('common.na') }}
                                        </span>
                                        <span v-if="item.group_name">/ {{ item.group_name }}</span>
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    icon="pi pi-history"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    :aria-label="t('common.history')"
                                    @click="openHistory(item)"
                                />
                            </div>

                            <div class="mt-3 inline-flex rounded-md border border-(--border) bg-(--muted)/45 px-2 py-1 text-xs font-medium text-(--muted-foreground)">
                                {{ t('homeworks.balance') }}: {{ item.points_balance ?? 0 }}
                            </div>

                            <div class="mt-3 grid gap-2 rounded-md border bg-(--background) p-2.5" :class="adjustmentToneClass(item)">
                                <label :for="`points-adjustment-${item.student_id}`" class="text-xs font-semibold">
                                    {{ t('homeworks.pointsAdjustment') }}
                                </label>
                                <input
                                    :id="`points-adjustment-${item.student_id}`"
                                    type="number"
                                    step="1"
                                    class="h-10 w-full rounded-md border border-(--border) bg-(--background) px-3 text-sm text-(--foreground) outline-none transition-colors focus:border-(--primary)"
                                    :value="item.points_adjustment ?? 0"
                                    @input="updatePointsAdjustment(item, $event)"
                                >
                                <div class="flex flex-wrap items-center gap-2 text-xs text-(--muted-foreground)">
                                    <span>{{ t('homeworks.pointsAdjustmentHint') }}</span>
                                    <span class="rounded-md border border-current/20 px-2 py-1 font-semibold">
                                        {{ t('homeworks.balanceAfterAdjustment') }}: {{ expectedBalanceAfterAdjustment(item) }}
                                    </span>
                                </div>
                                <small
                                    v-if="form.errors[`items.${itemIndex}.points_adjustment`]"
                                    class="text-xs text-red-600"
                                >
                                    {{ form.errors[`items.${itemIndex}.points_adjustment`] }}
                                </small>
                            </div>

                            <small
                                v-if="form.errors[`items.${itemIndex}.student_id`]"
                                class="mt-2 block text-xs text-red-600"
                            >
                                {{ form.errors[`items.${itemIndex}.student_id`] }}
                            </small>
                        </div>

                        <div class="rounded-md border border-(--border) bg-(--background) p-2.5 text-sm">
                            <p class="text-xs font-medium text-(--muted-foreground)">{{ t('homeworks.progress') }}</p>
                            <p class="mt-2 line-clamp-2 font-semibold leading-5">
                                {{ item.current_plan_point_name || t('homeworks.notStarted') }}
                            </p>
                        </div>

                        <div class="min-w-0">
                            <div v-if="!item.points?.length" class="rounded-md border border-dashed border-(--border) px-4 py-5 text-sm text-(--muted-foreground)">
                                {{ t('homeworks.noPlanPoints') }}
                            </div>

                            <div v-else class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-10">
                                <label
                                    v-for="(point, pointIndex) in item.points"
                                    :key="point.plan_point_id"
                                    class="flex min-h-20 cursor-pointer flex-col justify-between rounded-md border border-(--border) bg-(--background) p-2 text-xs transition-colors"
                                    :class="pointCardClass(point)"
                                >
                                    <span class="line-clamp-2 font-medium leading-4">{{ point.name }}</span>
                                    <span
                                        v-if="point.is_previous_next_homework"
                                        class="mt-1 inline-flex w-fit max-w-full items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold leading-4 text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100"
                                        :title="point.previous_next_homework_date_formatted"
                                    >
                                        {{ t('homeworks.previousNextHomework') }}
                                        <span
                                            v-if="shortPreviousHomeworkDate(point)"
                                            class="rounded-sm bg-amber-200/80 px-1 font-bold tabular-nums text-amber-950 dark:bg-amber-800/70 dark:text-amber-50"
                                        >
                                            {{ shortPreviousHomeworkDate(point) }}
                                        </span>
                                    </span>
                                    <span class="mt-1 text-xs text-(--muted-foreground)">
                                        {{ t('homeworks.pointValue', { points: point.points ?? 0 }) }}
                                    </span>
                                    <span class="mt-1.5 flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium">
                                            {{ point.is_locked ? t('homeworks.awarded') : t('homeworks.done') }}
                                        </span>
                                        <Checkbox
                                            :model-value="Boolean(point.is_done)"
                                            binary
                                            :input-id="`homework-${item.student_id}-${point.plan_point_id}`"
                                            :disabled="Boolean(point.is_locked)"
                                            @update:model-value="togglePoint(point, $event)"
                                        />
                                    </span>
                                    <span class="mt-1.5 flex items-center justify-between gap-2 rounded-md border border-(--border) px-2 py-1">
                                        <span class="text-xs font-medium text-(--muted-foreground)">
                                            {{ t('homeworks.nextHomework') }}
                                        </span>
                                        <Checkbox
                                            :model-value="Boolean(point.is_next_homework)"
                                            binary
                                            :input-id="`homework-next-${item.student_id}-${point.plan_point_id}`"
                                            :disabled="Boolean(point.is_locked)"
                                            @update:model-value="toggleNextHomework(point, $event)"
                                        />
                                    </span>
                                    <small
                                        v-if="form.errors[`items.${itemIndex}.points.${pointIndex}.plan_point_id`]"
                                        class="mt-2 text-xs text-red-600"
                                    >
                                        {{ form.errors[`items.${itemIndex}.points.${pointIndex}.plan_point_id`] }}
                                    </small>
                                </label>
                            </div>
                        </div>
                        </section>
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>

        <Dialog
            v-model:visible="historyVisible"
            modal
            :header="historyStudent ? `${t('homeworks.pointsHistory')} - ${historyStudent.full_name}` : t('homeworks.pointsHistory')"
            class="w-[min(920px,95vw)]"
        >
            <div v-if="historyLoading" class="py-6 text-sm text-(--muted-foreground)">
                {{ t('common.loading') }}
            </div>
            <div v-else-if="historyRows.length === 0" class="py-6 text-sm text-(--muted-foreground)">
                {{ t('homeworks.noHistory') }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-(--border)">
                    <thead>
                        <tr class="text-sm">
                            <th class="px-3 py-2 text-start font-semibold">{{ t('homeworks.historyDate') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ t('homeworks.planPoint') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ t('homeworks.points') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ t('homeworks.balanceAfter') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border)">
                        <tr v-for="row in historyRows" :key="row.id" class="text-sm">
                            <td class="px-3 py-2">{{ row.date }}</td>
                            <td class="px-3 py-2">{{ row.plan_point_name || t('common.na') }}</td>
                            <td class="px-3 py-2">{{ row.points }}</td>
                            <td class="px-3 py-2">{{ row.balance_after }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Dialog>
    </article>
</template>
