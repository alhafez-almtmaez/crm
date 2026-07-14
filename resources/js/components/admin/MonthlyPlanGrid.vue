<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    dates: {
        type: Array,
        default: () => [],
    },
    plans: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();

const dayOrder = [
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
];

const planRows = computed(() => props.plans.map((plan) => ({
    ...plan,
    dayMap: Object.fromEntries((plan.days ?? []).map((day) => [day.date, day])),
})));

const activeDayNames = computed(() => {
    const usedDays = new Set(
        props.dates
            .map((date) => String(date.day_name ?? '').toLowerCase())
            .filter((day) => dayOrder.includes(day)),
    );

    return dayOrder.filter((day) => usedDays.has(day));
});

const rowsWithSkippedItems = computed(() => planRows.value.filter((row) => row.skipped_items?.length));

const cellItems = (plan, date) => plan.dayMap?.[date]?.items ?? [];
const cellTotalWeight = (plan, date) => plan.dayMap?.[date]?.total_weight ?? 0;
const cellDailyLimit = (plan, date) => plan.dayMap?.[date]?.daily_weight_limit ?? plan.max_daily_weight ?? 0;
const dailyLimitForDay = (plan, day) => plan.daily_weight_limits?.[day] ?? plan.max_daily_weight ?? 0;
const cellCompletion = (plan, date) => plan.dayMap?.[date]?.completion ?? null;
const hasEligibleCompletion = (completion) => Number(completion?.eligible_items_count ?? 0) > 0;
const completionPercentage = (completion) => (
    completion?.percentage === null || completion?.percentage === undefined ? '—' : `${completion.percentage}%`
);
const completionProgress = (completion) => `${completion?.completed_items_count ?? 0}/${completion?.eligible_items_count ?? 0}`;

const completionTone = (completion) => completion?.tone ?? 'neutral';

const completionBadgeClass = (completion) => ({
    on_time: 'border-emerald-300 bg-emerald-50 text-emerald-900',
    different_day: 'border-orange-300 bg-orange-50 text-orange-900',
    missed: 'border-red-300 bg-red-50 text-red-900',
    future_completed: 'border-sky-300 bg-sky-50 text-sky-900',
}[completionTone(completion)] ?? 'border-(--border) bg-(--muted) text-(--muted-foreground)');

const cellToneClass = (plan, date) => {
    const tone = completionTone(cellCompletion(plan, date));

    return {
        on_time: 'bg-emerald-50/60',
        different_day: 'bg-orange-50/70',
        missed: 'bg-red-50/60',
        future_completed: 'bg-sky-50/60',
    }[tone] ?? '';
};

const itemCompletionClass = (item) => {
    if (item.completion_status === 'on_time') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-950';
    }

    if (item.completion_status === 'different_day') {
        return 'border-orange-300 bg-orange-50 text-orange-950';
    }

    if (item.completion_status === 'missed') {
        return 'border-red-300 bg-red-50 text-red-950';
    }

    if (item.completion_status === 'future_completed') {
        return 'border-sky-300 bg-sky-50 text-sky-950';
    }

    return 'border-(--border) bg-(--background) text-(--foreground)';
};

const itemStatusClass = (item) => ({
    on_time: 'bg-emerald-100 text-emerald-900',
    different_day: 'bg-orange-100 text-orange-900',
    missed: 'bg-red-100 text-red-900',
    future_completed: 'bg-sky-100 text-sky-900',
}[item.completion_status] ?? 'bg-(--muted) text-(--muted-foreground)');

const shouldShowItemStatus = (item) => [
    'on_time',
    'different_day',
    'missed',
    'future_completed',
].includes(item.completion_status);

const shortDate = (date) => {
    const [, month, day] = String(date).split('-').map((segment) => Number(segment));

    return month && day ? `${day}/${month}` : date;
};

const itemStatusLabel = (item) => {
    if (item.completion_status === 'on_time') {
        return t('monthlyPlans.completedOnPlanDate');
    }

    if (item.completion_status === 'different_day') {
        return t('monthlyPlans.completedOnDate', { date: shortDate(item.completed_on) });
    }

    if (item.completion_status === 'future_completed') {
        return t('monthlyPlans.completedBeforeDueDate', { date: shortDate(item.completed_on) });
    }

    return t('monthlyPlans.notCompleted');
};
</script>

<template>
    <div class="grid gap-6">
        <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">{{ t('monthlyPlans.planItems') }}</h2>
                    <p class="mt-1 text-sm text-(--muted-foreground)">
                        {{ t('monthlyPlans.monthlyGridHint') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-sm">
                    <span class="rounded-md border border-(--border) px-3 py-1 font-semibold">
                        {{ t('monthlyPlans.studentsCount') }}: {{ plans.length }}
                    </span>
                    <span class="rounded-md border border-(--border) px-3 py-1 font-semibold">
                        {{ t('monthlyPlans.workingDaysCount') }}: {{ dates.length }}
                    </span>
                </div>
            </div>

            <div v-if="plans.length === 0" class="mt-4 rounded-md border border-dashed border-(--border) p-6 text-sm text-(--muted-foreground)">
                {{ t('monthlyPlans.noPlans') }}
            </div>

            <div v-else class="mt-4 max-h-[calc(100vh-12rem)] overflow-auto rounded-md border border-(--border)">
                <table dir="rtl" class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th class="sticky top-0 right-0 z-40 min-w-72 border-b border-(--border) bg-(--card) px-3 py-3 text-start font-semibold shadow-(--shadow-sm)">
                                {{ t('monthlyPlans.student') }}
                            </th>
                            <th
                                v-for="date in dates"
                                :key="date.date"
                                class="sticky top-0 z-30 min-w-44 border-b border-(--border) bg-(--card) px-2 py-2 text-start align-top font-semibold shadow-(--shadow-sm)"
                            >
                                <span class="block">{{ shortDate(date.date) }}</span>
                                <span class="mt-1 block text-xs font-medium text-(--muted-foreground)">{{ date.day_label }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="plan in planRows" :key="plan.id" class="align-top">
                            <th class="sticky right-0 z-20 min-w-72 border-b border-(--border) bg-(--card) px-3 py-3 text-start shadow-(--shadow-sm)">
                                <span class="block font-semibold">{{ plan.student_name }}</span>
                                <span class="mt-1 block text-xs font-medium text-(--muted-foreground)">
                                    {{ plan.plan_name || t('common.na') }}
                                </span>
                                <div v-if="activeDayNames.length" class="mt-1">
                                    <span class="block text-xs text-(--muted-foreground)">
                                        {{ t('monthlyPlans.dailyWeightLimits') }}
                                    </span>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="day in activeDayNames"
                                            :key="`${plan.id}-${day}`"
                                            class="rounded-sm border border-(--border) bg-(--background) px-1.5 py-0.5 text-[11px] font-semibold text-(--foreground)"
                                        >
                                            {{ t(`days.${day}`) }}: {{ dailyLimitForDay(plan, day) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center rounded-md border px-2 py-1 text-[11px] font-semibold"
                                        :class="completionBadgeClass(plan.completion)"
                                    >
                                        {{ t('monthlyPlans.completionPercentage') }}: {{ completionPercentage(plan.completion) }}
                                    </span>
                                    <span class="text-[11px] text-(--muted-foreground)">
                                        {{ t('monthlyPlans.completedItems') }}: {{ completionProgress(plan.completion) }}
                                    </span>
                                </div>
                            </th>
                            <td
                                v-for="date in dates"
                                :key="`${plan.id}-${date.date}`"
                                class="min-w-44 border-b border-(--border) px-1.5 py-1.5 align-top"
                                :class="cellToneClass(plan, date.date)"
                            >
                                <div v-if="cellItems(plan, date.date).length" class="grid gap-1.5">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span
                                            v-if="hasEligibleCompletion(cellCompletion(plan, date.date))"
                                            class="rounded-md border px-2 py-1 text-[11px] font-semibold"
                                            :class="completionBadgeClass(cellCompletion(plan, date.date))"
                                        >
                                            {{ t('monthlyPlans.dayCompletion') }}:
                                            {{ completionPercentage(cellCompletion(plan, date.date)) }}
                                            ({{ completionProgress(cellCompletion(plan, date.date)) }})
                                        </span>
                                        <span class="rounded-md border border-(--border) bg-(--card) px-2 py-1 text-[11px] font-semibold text-(--muted-foreground)">
                                            {{ t('monthlyPlans.totalWeight') }}: {{ cellTotalWeight(plan, date.date) }}
                                            / {{ t('monthlyPlans.dailyWeightLimit') }}: {{ cellDailyLimit(plan, date.date) }}
                                        </span>
                                    </div>
                                    <div
                                        v-for="item in cellItems(plan, date.date)"
                                        :key="item.id"
                                        class="rounded-md border px-2 py-1.5"
                                        :class="itemCompletionClass(item)"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="line-clamp-2 text-xs font-semibold leading-5">{{ item.name }}</div>
                                            <span class="shrink-0 rounded-sm bg-(--muted) px-1.5 py-0.5 text-[11px] font-semibold">
                                                {{ item.weight }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-1">
                                            <span v-if="item.is_standalone" class="rounded-sm bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900">
                                                {{ t('monthlyPlans.standalone') }}
                                            </span>
                                            <span
                                                v-if="shouldShowItemStatus(item)"
                                                class="rounded-sm px-1.5 py-0.5 text-[11px] font-semibold"
                                                :class="itemStatusClass(item)"
                                            >
                                                {{ itemStatusLabel(item) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article v-if="rowsWithSkippedItems.length" class="rounded-(--radius-base) border border-amber-300 bg-amber-50 p-5 text-amber-950 shadow-(--shadow-sm)">
            <h2 class="text-lg font-semibold">{{ t('monthlyPlans.skippedItems') }}</h2>
            <div class="mt-3 grid gap-3">
                <div
                    v-for="plan in rowsWithSkippedItems"
                    :key="`skipped-${plan.id}`"
                    class="rounded-md border border-amber-300 bg-white p-3 text-sm"
                >
                    <p class="font-semibold">{{ plan.student_name }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="item in plan.skipped_items"
                            :key="item.id"
                            class="rounded-md border border-amber-300 bg-white px-2 py-1"
                        >
                            {{ item.name }} / {{ t('monthlyPlans.weight') }}: {{ item.weight }}
                        </span>
                    </div>
                </div>
            </div>
        </article>
    </div>
</template>
