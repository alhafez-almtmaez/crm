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

const planRows = computed(() => props.plans.map((plan) => ({
    ...plan,
    dayMap: Object.fromEntries((plan.days ?? []).map((day) => [day.date, day])),
})));

const rowsWithSkippedItems = computed(() => planRows.value.filter((row) => row.skipped_items?.length));

const cellItems = (plan, date) => plan.dayMap?.[date]?.items ?? [];
const cellTotalWeight = (plan, date) => plan.dayMap?.[date]?.total_weight ?? 0;

const shortDate = (date) => {
    const [, month, day] = String(date).split('-').map((segment) => Number(segment));

    return month && day ? `${day}/${month}` : date;
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

            <div v-else class="mt-4 overflow-x-auto">
                <table dir="rtl" class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th class="sticky right-0 z-20 min-w-56 border-b border-(--border) bg-(--card) px-3 py-3 text-start font-semibold">
                                {{ t('monthlyPlans.student') }}
                            </th>
                            <th
                                v-for="date in dates"
                                :key="date.date"
                                class="border-b border-(--border) px-2 py-2 text-start align-top font-semibold"
                            >
                                <span class="block">{{ shortDate(date.date) }}</span>
                                <span class="mt-1 block text-xs font-medium text-(--muted-foreground)">{{ date.day_label }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="plan in planRows" :key="plan.id" class="align-top">
                            <th class="sticky right-0 z-10 min-w-56 border-b border-(--border) bg-(--card) px-3 py-3 text-start">
                                <span class="block font-semibold">{{ plan.student_name }}</span>
                                <span class="mt-1 block text-xs font-medium text-(--muted-foreground)">
                                    {{ plan.plan_name || t('common.na') }}
                                </span>
                                <span class="mt-1 block text-xs text-(--muted-foreground)">
                                    {{ t('monthlyPlans.maxDailyWeight') }}: {{ plan.max_daily_weight }}
                                </span>
                            </th>
                            <td
                                v-for="date in dates"
                                :key="`${plan.id}-${date.date}`"
                                class="border-b border-(--border) px-1.5 py-1.5 align-top"
                            >
                                <div v-if="cellItems(plan, date.date).length" class="grid gap-1">
                                    <div
                                        v-for="item in cellItems(plan, date.date)"
                                        :key="item.id"
                                        class="rounded-sm border border-(--border) bg-(--background) px-1.5 py-1"
                                    >
                                        <div class="line-clamp-1 text-xs font-semibold leading-4">{{ item.name }}</div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                            <span class="rounded-sm bg-(--muted) px-1.5 py-0.5 text-[11px] font-semibold">
                                                {{ item.weight }}
                                            </span>
                                            <span v-if="item.is_standalone" class="rounded-sm bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900">
                                                {{ t('monthlyPlans.standalone') }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-[11px] font-semibold text-(--muted-foreground)">
                                        {{ t('monthlyPlans.totalWeight') }}: {{ cellTotalWeight(plan, date.date) }}
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
