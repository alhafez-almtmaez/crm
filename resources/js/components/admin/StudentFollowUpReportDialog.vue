<script setup>
import axios from 'axios';
import { Chart, registerables } from 'chart.js';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { planStatusBadgeClass } from '../../admin/planStatusStyles';
import DashboardChartCard from './dashboard/DashboardChartCard.vue';
import ReportChartEmptyState from './ReportChartEmptyState.vue';

Chart.register(...registerables);

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    student: { type: Object, default: null },
    reportUrl: { type: String, default: '' },
    hasUnsavedChanges: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const { t, locale } = useI18n();
const loading = ref(false);
const error = ref('');
const report = ref(null);
const activeTab = ref('attendance');
let requestSequence = 0;

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'short' }).format(date);
};

const formatReportDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(date);
};

const loadReport = async () => {
    if (!props.modelValue || !props.reportUrl) return;

    const sequence = ++requestSequence;
    loading.value = true;
    error.value = '';
    report.value = null;

    try {
        const { data } = await axios.get(props.reportUrl);
        if (sequence === requestSequence) report.value = data;
    } catch (exception) {
        if (sequence === requestSequence) {
            error.value = exception?.response?.data?.message || t('dailyFollowUp.reportLoadFailed');
        }
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
};

watch(
    () => [props.modelValue, props.reportUrl],
    ([isOpen]) => {
        if (isOpen) {
            activeTab.value = 'attendance';
            loadReport();
        }
    },
);

const summaryCards = computed(() => {
    const summary = report.value?.summary ?? {};

    return [
        { key: 'plan', icon: 'pi pi-map', label: t('dailyFollowUp.planProgress'), value: summary.plan_progress, suffix: '%' },
        { key: 'adherence', icon: 'pi pi-compass', label: t('dailyFollowUp.planAdherence'), value: summary.plan_adherence, suffix: '%' },
        { key: 'attendance', icon: 'pi pi-calendar-check', label: t('dailyFollowUp.attendanceRate'), value: summary.attendance_rate, suffix: '%' },
        { key: 'evaluation', icon: 'pi pi-chart-line', label: t('dailyFollowUp.evaluationAverage'), value: summary.evaluation_average, suffix: '%' },
    ];
});

const chartLabels = (values = []) => values.map(formatDate);
const attendanceChart = computed(() => {
    const counts = report.value?.attendance?.counts ?? {};

    return {
        labels: [
            t('evaluations.present'),
            t('evaluations.late'),
            t('evaluations.excusedAbsence'),
            t('evaluations.absence'),
            t('evaluations.exempt'),
        ],
        datasets: [{
            data: [counts.present ?? 0, counts.late ?? 0, counts.excused ?? 0, counts.absent ?? 0, counts.exempt ?? 0],
            backgroundColor: ['#059669', '#0284c7', '#d97706', '#e11d48', '#64748b'],
            borderWidth: 0,
            hoverOffset: 7,
        }],
    };
});
const hasAttendanceData = computed(() => Object.values(report.value?.attendance?.counts ?? {})
    .some((value) => Number(value) > 0));
const evaluationDatasets = computed(() => {
    const evaluation = report.value?.evaluation ?? {};

    return [
        { key: 'alhifz', color: '#0284c7' },
        { key: 'tajwid', color: '#d97706' },
        { key: 'warud', color: '#7c3aed' },
        { key: 'akhlaqi', color: '#059669' },
    ].filter(({ key }) => (evaluation[key] ?? []).some((value) => value !== null && value !== undefined));
});
const evaluationChart = computed(() => {
    const evaluation = report.value?.evaluation ?? {};

    return {
        labels: chartLabels(evaluation.labels),
        datasets: evaluationDatasets.value.map(({ key, color }) => ({
            label: t(`evaluations.${key}`),
            data: evaluation[key] ?? [],
            borderColor: color,
            backgroundColor: `${color}1f`,
            tension: 0.32,
            pointRadius: 3,
        })),
    };
});
const hasEvaluationData = computed(() => evaluationDatasets.value.length > 0);
const progressChart = computed(() => {
    const achievement = report.value?.achievement ?? {};

    return {
        labels: chartLabels(achievement.labels),
        datasets: [
            { label: t('dailyFollowUp.expectedProgress'), data: achievement.expected_cumulative ?? [], borderColor: '#64748b', borderDash: [7, 5], backgroundColor: 'rgba(100,116,139,.08)', tension: 0.28, pointRadius: 2 },
            { label: t('dailyFollowUp.actualProgress'), data: achievement.completed_cumulative ?? [], borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.14)', fill: true, tension: 0.28, pointRadius: 3 },
        ],
    };
});
const homeworkChart = computed(() => {
    const achievement = report.value?.achievement ?? {};

    return {
        labels: chartLabels(achievement.labels),
        datasets: [
            { label: t('dailyFollowUp.plannedItems'), data: achievement.planned_daily ?? [], backgroundColor: '#64748b', borderRadius: 6 },
            { label: t('dailyFollowUp.assignedItems'), data: achievement.assigned_daily ?? [], backgroundColor: '#0284c7', borderRadius: 6 },
            { label: t('dailyFollowUp.completedItems'), data: achievement.completed_daily ?? [], backgroundColor: '#059669', borderRadius: 6 },
            { label: t('dailyFollowUp.outsidePlanItems'), data: achievement.outside_plan_daily ?? [], backgroundColor: '#d97706', borderRadius: 6 },
        ],
    };
});
const hasProgressData = computed(() => (report.value?.achievement?.labels ?? []).length > 0);
const hasHomeworkData = computed(() => [
    ...(report.value?.achievement?.assigned_daily ?? []),
    ...(report.value?.achievement?.completed_daily ?? []),
    ...(report.value?.achievement?.outside_plan_daily ?? []),
].some((value) => Number(value) > 0));

const basePlugins = {
    legend: { position: 'bottom', labels: { color: '#64748b', usePointStyle: true, boxWidth: 8, boxHeight: 8 } },
    tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 11 },
};
const lineOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: basePlugins,
    scales: {
        x: { grid: { display: false }, ticks: { color: '#64748b' } },
        y: { beginAtZero: true, suggestedMax: 100, grid: { color: 'rgba(100,116,139,.16)' }, ticks: { color: '#64748b' } },
    },
}));
const scoreOptions = computed(() => ({
    ...lineOptions.value,
    scales: { ...lineOptions.value.scales, y: { ...lineOptions.value.scales.y, max: 10 } },
}));
const doughnutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '66%',
    plugins: basePlugins,
}));
const barOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: basePlugins,
    scales: {
        x: { grid: { display: false }, ticks: { color: '#64748b' } },
        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(100,116,139,.16)' } },
    },
}));
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        maximizable
        :header="t('dailyFollowUp.studentReport')"
        :style="{ width: 'min(98vw, 96rem)' }"
        :breakpoints="{ '960px': '98vw' }"
    >
        <div v-if="loading" class="grid min-h-96 place-items-center text-(--muted-foreground)">
            <div class="text-center">
                <i class="pi pi-spin pi-spinner text-3xl text-[var(--accent)]" aria-hidden="true"></i>
                <p class="mt-3 text-sm">{{ t('common.loading') }}</p>
            </div>
        </div>

        <div v-else-if="error" class="grid min-h-80 place-items-center">
            <div class="max-w-lg text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-red-50 text-red-700 dark:bg-red-950/35 dark:text-red-200">
                    <i class="pi pi-exclamation-triangle" aria-hidden="true"></i>
                </span>
                <p class="mt-4 text-sm text-(--muted-foreground)">{{ error }}</p>
                <Button class="mt-4" severity="secondary" outlined icon="pi pi-refresh" :label="t('dailyFollowUp.retry')" @click="loadReport" />
            </div>
        </div>

        <div v-else-if="report" class="space-y-3">
            <header class="overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--card)">
                <div class="grid gap-3 bg-[linear-gradient(135deg,color-mix(in_oklab,var(--accent)_10%,var(--card)),var(--card)_62%)] p-3 sm:p-4 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="me-1 truncate text-lg font-bold">{{ report.student.name }}</h2>
                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold shadow-sm" :class="planStatusBadgeClass(report.tracking.status)">
                                {{ t(`dailyFollowUp.planStatuses.${report.tracking.status}`) }}
                            </span>
                            <span class="text-[11px] text-(--muted-foreground)">{{ report.period.label }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-(--border) bg-(--background) px-2 py-0.5 text-[11px] font-semibold text-(--foreground)">
                                <i class="pi pi-filter" aria-hidden="true"></i>
                                {{ t('dailyFollowUp.reportAsOf', { date: formatReportDate(report.period.as_of_date) }) }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-(--muted-foreground)">{{ report.group.name }} · {{ report.student.plan_name }}</p>
                    </div>
                    <a
                        v-if="report.monthly_plan.edit_url"
                        :href="report.monthly_plan.edit_url"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-(--border) bg-(--background) px-3 text-xs font-semibold transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
                    >
                        <i class="pi pi-map" aria-hidden="true"></i>
                        {{ t('dailyFollowUp.openMonthlyPlan') }}
                    </a>
                </div>
            </header>

            <div v-if="hasUnsavedChanges" class="rounded-lg border border-[color-mix(in_oklab,var(--status-warning)_55%,var(--border))] bg-[color-mix(in_oklab,var(--status-warning)_16%,var(--card))] px-3 py-2 text-xs text-(--foreground)">
                <i class="pi pi-info-circle me-2" aria-hidden="true"></i>
                {{ t('dailyFollowUp.reportUnsavedNotice') }}
            </div>

            <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-(--border) bg-(--border) sm:grid-cols-4">
                <article v-for="card in summaryCards" :key="card.key" class="flex items-center gap-2 bg-(--card) px-3 py-2.5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-md bg-[color-mix(in_oklab,var(--accent)_10%,transparent)] text-xs text-[var(--accent)]">
                        <i :class="card.icon" aria-hidden="true"></i>
                    </span>
                    <div class="min-w-0">
                        <strong class="block text-lg leading-5">{{ card.value === null || card.value === undefined ? '—' : `${card.value}${card.suffix}` }}</strong>
                        <p class="truncate text-[11px] text-(--muted-foreground)">{{ card.label }}</p>
                    </div>
                </article>
            </div>

            <div class="grid grid-cols-2 gap-1 rounded-xl border border-(--border) bg-(--background) p-1">
                <button
                    v-for="tab in ['attendance', 'achievement']"
                    :key="tab"
                    type="button"
                    class="min-h-9 rounded-lg px-3 text-sm font-semibold transition"
                    :class="activeTab === tab ? 'bg-[color-mix(in_oklab,var(--accent)_13%,var(--card))] text-[var(--accent)] shadow-sm' : 'text-(--muted-foreground) hover:text-(--foreground)'"
                    @click="activeTab = tab"
                >
                    {{ t(`dailyFollowUp.reportTabs.${tab}`) }}
                </button>
            </div>

            <div v-if="activeTab === 'attendance'" class="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
                <DashboardChartCard v-if="hasAttendanceData" :title="t('dailyFollowUp.attendanceDistribution')" type="doughnut" :chart-data="attendanceChart" :options="doughnutOptions" :height="340" />
                <ReportChartEmptyState v-else :title="t('dailyFollowUp.attendanceDistribution')" :description="t('dailyFollowUp.noAttendanceData')" icon="pi pi-calendar" />
                <DashboardChartCard v-if="hasEvaluationData" :title="t('dailyFollowUp.evaluationTrend')" type="line" :chart-data="evaluationChart" :options="scoreOptions" :height="340" />
                <ReportChartEmptyState v-else :title="t('dailyFollowUp.evaluationTrend')" :description="t('dailyFollowUp.noEvaluationData')" />
            </div>
            <div v-else class="grid gap-4 xl:grid-cols-2">
                <DashboardChartCard v-if="hasProgressData" :title="t('dailyFollowUp.planProgressChart')" type="line" :chart-data="progressChart" :options="lineOptions" :height="340" />
                <ReportChartEmptyState v-else :title="t('dailyFollowUp.planProgressChart')" :description="t('dailyFollowUp.noPlanData')" icon="pi pi-map" />
                <DashboardChartCard v-if="hasHomeworkData" :title="t('dailyFollowUp.homeworkAchievementChart')" type="bar" :chart-data="homeworkChart" :options="barOptions" :height="340" />
                <ReportChartEmptyState v-else :title="t('dailyFollowUp.homeworkAchievementChart')" :description="t('dailyFollowUp.noHomeworkData')" icon="pi pi-check-square" />
            </div>

            <details class="rounded-xl border border-[color-mix(in_oklab,var(--accent)_24%,var(--border))] bg-[color-mix(in_oklab,var(--accent)_6%,var(--card))] px-3 py-2">
                <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-semibold text-[var(--accent)] [&::-webkit-details-marker]:hidden">
                    <i class="pi pi-sparkles" aria-hidden="true"></i>
                    {{ t('dailyFollowUp.smartInsight') }}
                    <i class="pi pi-chevron-down ms-auto text-xs" aria-hidden="true"></i>
                </summary>
                <p class="mt-2 text-sm leading-6 text-(--muted-foreground)">{{ t(`dailyFollowUp.insights.${report.insight}`) }}</p>
            </details>
        </div>
    </Dialog>
</template>
