<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import DashboardChartCard from '../../components/admin/dashboard/DashboardChartCard.vue';
import DashboardListCard from '../../components/admin/dashboard/DashboardListCard.vue';
import DashboardMetricCard from '../../components/admin/dashboard/DashboardMetricCard.vue';
import { useDashboardCharts } from '../../composables/useDashboardCharts';

const props = defineProps({
    dashboard: {
        type: Object,
        default: () => ({}),
    },
});

const { t } = useI18n();
const dashboardRef = computed(() => props.dashboard ?? {});
const summary = computed(() => dashboardRef.value.summary ?? {});
const numberFormatter = computed(() => new Intl.NumberFormat('en-US'));
const {
    studentStatusChartData,
    attendanceTrendChartData,
    homeworkProgressChartData,
    lineOptions,
    doughnutOptions,
    stackedBarOptions,
} = useDashboardCharts(dashboardRef, t);

const formatNumber = (value) => numberFormatter.value.format(Number(value ?? 0));
const formatPercent = (value) => t('dashboard.percentValue', {
    value: formatNumber(value),
});

const metricCards = computed(() => [
    {
        key: 'students',
        label: t('dashboard.metrics.students'),
        value: formatNumber(summary.value.students_total),
        subtitle: t('dashboard.metrics.studentsSubtitle', {
            active: formatNumber(summary.value.active_students),
            frozen: formatNumber(summary.value.frozen_students),
        }),
        icon: 'pi pi-users',
        tone: 'emerald',
    },
    {
        key: 'attendance',
        label: t('dashboard.metrics.attendanceRate'),
        value: formatPercent(summary.value.attendance_rate_last_30),
        subtitle: t('dashboard.metrics.attendanceSubtitle', {
            present: formatNumber(summary.value.attendance_present_last_30),
            total: formatNumber(summary.value.attendance_total_last_30),
        }),
        icon: 'pi pi-calendar-check',
        tone: 'sky',
    },
    {
        key: 'homeworks',
        label: t('dashboard.metrics.homeworkPoints'),
        value: formatNumber(summary.value.homework_points_completed_month),
        subtitle: t('dashboard.metrics.homeworkSubtitle', {
            points: formatNumber(summary.value.homework_points_awarded_month),
            count: formatNumber(summary.value.homeworks_this_month),
        }),
        icon: 'pi pi-check-square',
        tone: 'violet',
    },
    {
        key: 'evaluations',
        label: t('dashboard.metrics.evaluations'),
        value: formatNumber(summary.value.evaluations_this_month),
        subtitle: t('dashboard.metrics.evaluationsSubtitle', {
            centers: formatNumber(summary.value.centers_total),
            groups: formatNumber(summary.value.groups_total),
        }),
        icon: 'pi pi-clipboard',
        tone: 'amber',
    },
]);

const summaryStrip = computed(() => [
    {
        key: 'centers',
        icon: 'pi pi-building',
        value: t('dashboard.summaryStrip.centers', {
            count: formatNumber(summary.value.centers_total),
        }),
    },
    {
        key: 'groups',
        icon: 'pi pi-sitemap',
        value: t('dashboard.summaryStrip.groups', {
            count: formatNumber(summary.value.groups_total),
        }),
    },
    {
        key: 'plans',
        icon: 'pi pi-bookmark',
        value: t('dashboard.summaryStrip.plans', {
            count: formatNumber(summary.value.plans_total),
        }),
    },
    {
        key: 'whatsapp',
        icon: 'pi pi-whatsapp',
        value: t('dashboard.summaryStrip.whatsapp', {
            connected: formatNumber(summary.value.whatsapp_connected_devices),
            total: formatNumber(summary.value.whatsapp_devices_total),
        }),
    },
]);

const alertIcons = {
    studentsWithoutPlan: 'pi pi-bookmark',
    studentsWithoutGroup: 'pi pi-sitemap',
    failedAbsenceMessages: 'pi pi-send',
    recentEvaluations: 'pi pi-clipboard',
    whatsappDevices: 'pi pi-whatsapp',
};

const alertItems = computed(() => (dashboardRef.value.alerts ?? []).map((item) => ({
    key: item.key,
    title: t(`dashboard.alerts.${item.key}.title`),
    meta: item.key === 'whatsappDevices'
        ? t(`dashboard.alerts.${item.key}.description`, {
            connected: formatNumber(item.count),
            total: formatNumber(item.total),
        })
        : t(`dashboard.alerts.${item.key}.description`, {
            count: formatNumber(item.count),
        }),
    value: item.total === undefined
        ? formatNumber(item.count)
        : `${formatNumber(item.count)}/${formatNumber(item.total)}`,
    href: item.href,
    icon: alertIcons[item.key] ?? 'pi pi-info-circle',
    tone: item.tone ?? 'neutral',
})));

const centerItems = computed(() => (dashboardRef.value.center_performance ?? []).map((center) => ({
    key: `center-${center.id}`,
    title: center.name,
    meta: t('dashboard.centerPerformance.meta', {
        active: formatNumber(center.active_students_count),
        students: formatNumber(center.students_count),
        groups: formatNumber(center.groups_count),
    }),
    value: t('dashboard.centerPerformance.activityValue', {
        evaluations: formatNumber(center.evaluations_count),
        homeworks: formatNumber(center.homeworks_count),
    }),
    href: `/admin/centers/${center.id}/edit`,
    icon: 'pi pi-building',
    tone: 'info',
})));

const activityTone = {
    evaluation: 'info',
    homework: 'success',
    absence: 'warning',
};

const recentActivityItems = computed(() => (dashboardRef.value.recent_activity ?? []).map((item, index) => ({
    key: `${item.type}-${index}-${item.date}`,
    title: item.title,
    meta: `${item.meta} - ${item.date}`,
    eyebrow: t(`dashboard.activityTypes.${item.type}`),
    href: item.href,
    icon: item.icon,
    tone: activityTone[item.type] ?? 'neutral',
})));

const quickActions = computed(() => [
    {
        key: 'student',
        title: t('dashboard.actions.createStudent'),
        meta: t('dashboard.actions.createStudentMeta'),
        href: '/admin/students/create',
        icon: 'pi pi-user-plus',
        tone: 'success',
    },
    {
        key: 'evaluation',
        title: t('dashboard.actions.createEvaluation'),
        meta: t('dashboard.actions.createEvaluationMeta'),
        href: '/admin/evaluations/create',
        icon: 'pi pi-clipboard',
        tone: 'info',
    },
    {
        key: 'homework',
        title: t('dashboard.actions.createHomework'),
        meta: t('dashboard.actions.createHomeworkMeta'),
        href: '/admin/homeworks/create',
        icon: 'pi pi-check-square',
        tone: 'warning',
    },
]);
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('dashboard.title')">
        <section class="dashboard-page">
            <AdminBreadcrumbs />

            <header class="dashboard-hero">
                <span class="dashboard-hero__accent" aria-hidden="true"></span>
                <div class="dashboard-hero__copy">
                    <p class="dashboard-hero__eyebrow">{{ t('dashboard.eyebrow') }}</p>
                    <h2 class="dashboard-hero__title">{{ t('dashboard.title') }}</h2>
                    <p class="dashboard-hero__subtitle">{{ t('dashboard.subtitle') }}</p>
                </div>

                <div class="dashboard-hero__strip" :aria-label="t('dashboard.summaryStrip.ariaLabel')">
                    <span
                        v-for="item in summaryStrip"
                        :key="item.key"
                        class="dashboard-hero__strip-item"
                    >
                        <i :class="item.icon" aria-hidden="true"></i>
                        {{ item.value }}
                    </span>
                </div>
            </header>

            <section class="dashboard-metrics">
                <DashboardMetricCard
                    v-for="card in metricCards"
                    :key="card.key"
                    :label="card.label"
                    :value="card.value"
                    :subtitle="card.subtitle"
                    :icon="card.icon"
                    :tone="card.tone"
                />
            </section>

            <section class="dashboard-grid dashboard-grid--primary">
                <DashboardChartCard
                    :title="t('dashboard.charts.attendanceTrend')"
                    :subtitle="t('dashboard.charts.attendanceTrendSubtitle')"
                    type="line"
                    :chart-data="attendanceTrendChartData"
                    :options="lineOptions"
                    :height="320"
                />
                <DashboardChartCard
                    :title="t('dashboard.charts.studentStatus')"
                    :subtitle="t('dashboard.charts.studentStatusSubtitle')"
                    type="doughnut"
                    :chart-data="studentStatusChartData"
                    :options="doughnutOptions"
                    :height="320"
                />
            </section>

            <section class="dashboard-grid dashboard-grid--secondary">
                <DashboardChartCard
                    :title="t('dashboard.charts.homeworkProgress')"
                    :subtitle="t('dashboard.charts.homeworkProgressSubtitle')"
                    type="bar"
                    :chart-data="homeworkProgressChartData"
                    :options="stackedBarOptions"
                    :height="280"
                />
                <DashboardListCard
                    :title="t('dashboard.alerts.title')"
                    :subtitle="t('dashboard.alerts.subtitle')"
                    :items="alertItems"
                    :empty-text="t('dashboard.empty')"
                />
            </section>

            <section class="dashboard-lists">
                <DashboardListCard
                    :title="t('dashboard.centerPerformance.title')"
                    :subtitle="t('dashboard.centerPerformance.subtitle')"
                    :items="centerItems"
                    :empty-text="t('dashboard.empty')"
                />
                <DashboardListCard
                    :title="t('dashboard.recentActivity.title')"
                    :subtitle="t('dashboard.recentActivity.subtitle')"
                    :items="recentActivityItems"
                    :empty-text="t('dashboard.empty')"
                />
                <DashboardListCard
                    :title="t('dashboard.actions.title')"
                    :subtitle="t('dashboard.actions.subtitle')"
                    :items="quickActions"
                    :empty-text="t('dashboard.empty')"
                />
            </section>
        </section>
    </AdminLayout>
</template>

<style scoped>
.dashboard-page {
    display: grid;
    gap: 1.15rem;
}

.dashboard-hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 0.58fr);
    align-items: end;
    gap: 1.4rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-base);
    background:
        linear-gradient(120deg, color-mix(in oklab, var(--accent) 9%, transparent), transparent 42%),
        linear-gradient(180deg, color-mix(in oklab, var(--background) 68%, transparent), transparent),
        var(--card);
    color: var(--card-foreground);
    padding: clamp(1.15rem, 3vw, 1.65rem);
    box-shadow: 0 16px 40px rgb(15 23 42 / 0.06), var(--shadow-sm);
}

:global(:root.dark) .dashboard-hero {
    box-shadow: 0 18px 46px rgb(0 0 0 / 0.26), var(--shadow-sm);
}

.dashboard-hero__accent {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: 0.34rem;
    background: linear-gradient(180deg, var(--accent), #0284c7, #7c3aed);
}

.dashboard-hero__copy {
    max-width: 48rem;
}

.dashboard-hero__eyebrow {
    margin: 0 0 0.45rem;
    color: var(--accent);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}

.dashboard-hero__title {
    margin: 0;
    font-size: clamp(1.9rem, 3.4vw, 2.75rem);
    font-weight: 850;
    line-height: 1.1;
    letter-spacing: 0;
}

.dashboard-hero__subtitle {
    margin: 0.8rem 0 0;
    color: var(--muted-foreground);
    font-size: 1rem;
    line-height: 1.75;
}

.dashboard-hero__strip {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.dashboard-hero__strip-item {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: color-mix(in oklab, var(--background) 86%, transparent);
    color: var(--foreground);
    font-size: 0.86rem;
    font-weight: 750;
    line-height: 1.2;
    min-height: 2.8rem;
    padding: 0.7rem 0.75rem;
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.32);
}

.dashboard-hero__strip-item .pi {
    color: var(--accent);
    font-size: 0.98rem;
}

.dashboard-metrics {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
}

.dashboard-grid {
    display: grid;
    gap: 1.15rem;
}

.dashboard-grid--primary {
    grid-template-columns: minmax(0, 1.65fr) minmax(18rem, 0.9fr);
}

.dashboard-grid--secondary {
    grid-template-columns: minmax(0, 1.35fr) minmax(18rem, 0.9fr);
}

.dashboard-lists {
    display: grid;
    gap: 1.15rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

@media (max-width: 1180px) {
    .dashboard-hero,
    .dashboard-grid--primary,
    .dashboard-grid--secondary,
    .dashboard-lists {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .dashboard-hero__title {
        font-size: 2rem;
    }

    .dashboard-hero__strip {
        grid-template-columns: 1fr;
    }

    .dashboard-hero__strip-item {
        width: 100%;
    }
}
</style>
