import { Chart, registerables } from 'chart.js';
import { computed, unref } from 'vue';

Chart.register(...registerables);

const chartColors = {
    emerald: '#059669',
    teal: '#0d9488',
    sky: '#0284c7',
    amber: '#d97706',
    rose: '#e11d48',
    slate: '#64748b',
    violet: '#7c3aed',
};

const statusColorMap = {
    active: chartColors.emerald,
    frozen: chartColors.amber,
    inactive: chartColors.rose,
};

const axisColor = '#64748b';
const gridColor = 'rgba(100, 116, 139, 0.18)';

const buildScales = (stacked = false) => ({
    x: {
        stacked,
        ticks: {
            color: axisColor,
            maxRotation: 0,
            autoSkip: true,
        },
        grid: {
            display: false,
        },
    },
    y: {
        stacked,
        beginAtZero: true,
        ticks: {
            precision: 0,
            color: axisColor,
        },
        grid: {
            color: gridColor,
        },
    },
});

const basePlugins = {
    legend: {
        position: 'bottom',
        labels: {
            boxHeight: 8,
            boxWidth: 8,
            color: axisColor,
            usePointStyle: true,
        },
    },
    tooltip: {
        backgroundColor: '#0f172a',
        titleColor: '#ffffff',
        bodyColor: '#e2e8f0',
        borderColor: 'rgba(148, 163, 184, 0.2)',
        borderWidth: 1,
        padding: 12,
    },
};

export function useDashboardCharts(dashboardRef, t) {
    const dashboard = computed(() => unref(dashboardRef) ?? {});

    const studentStatusChartData = computed(() => {
        const rows = dashboard.value.student_status ?? [];

        return {
            labels: rows.map((row) => t(`dashboard.statuses.${row.key}`)),
            datasets: [
                {
                    data: rows.map((row) => Number(row.count ?? 0)),
                    backgroundColor: rows.map((row) => statusColorMap[row.key] ?? chartColors.slate),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6,
                },
            ],
        };
    });

    const attendanceTrendChartData = computed(() => {
        const trend = dashboard.value.attendance_trend ?? {};

        return {
            labels: trend.labels ?? [],
            datasets: [
                {
                    label: t('dashboard.attendance.present'),
                    data: trend.present ?? [],
                    borderColor: chartColors.emerald,
                    backgroundColor: 'rgba(5, 150, 105, 0.14)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                },
                {
                    label: t('dashboard.attendance.absent'),
                    data: trend.absent ?? [],
                    borderColor: chartColors.rose,
                    backgroundColor: 'rgba(225, 29, 72, 0.12)',
                    fill: false,
                    tension: 0.35,
                    pointRadius: 2,
                },
                {
                    label: t('dashboard.attendance.excused'),
                    data: trend.excused ?? [],
                    borderColor: chartColors.amber,
                    backgroundColor: 'rgba(217, 119, 6, 0.12)',
                    fill: false,
                    tension: 0.35,
                    pointRadius: 2,
                },
            ],
        };
    });

    const homeworkProgressChartData = computed(() => {
        const rows = dashboard.value.homework_progress ?? [];

        return {
            labels: rows.map((row) => row.label),
            datasets: [
                {
                    label: t('dashboard.homeworks.completed'),
                    data: rows.map((row) => Number(row.completed ?? 0)),
                    backgroundColor: chartColors.sky,
                    borderRadius: 6,
                },
                {
                    label: t('dashboard.homeworks.pending'),
                    data: rows.map((row) => Number(row.pending ?? 0)),
                    backgroundColor: 'rgba(100, 116, 139, 0.35)',
                    borderRadius: 6,
                },
            ],
        };
    });

    const lineOptions = computed(() => ({
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index',
        },
        plugins: basePlugins,
        scales: buildScales(false),
    }));

    const doughnutOptions = computed(() => ({
        responsive: true,
        maintainAspectRatio: false,
        cutout: '64%',
        plugins: basePlugins,
    }));

    const stackedBarOptions = computed(() => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: basePlugins,
        scales: buildScales(true),
    }));

    return {
        studentStatusChartData,
        attendanceTrendChartData,
        homeworkProgressChartData,
        lineOptions,
        doughnutOptions,
        stackedBarOptions,
    };
}
