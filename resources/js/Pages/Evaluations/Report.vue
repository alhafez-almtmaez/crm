<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const copied = ref(false);
const rows = computed(() => props.report.rows ?? []);
const logoUrl = computed(() => '/media/logos/logo.png');
const absoluteLogoUrl = computed(() => {
    if (typeof window === 'undefined') {
        return logoUrl.value;
    }

    return new URL(logoUrl.value, window.location.origin).toString();
});
const scoreLabel = computed(() => props.report.primary_score_label || 'الحفظ');
const metaDescription = computed(() => `${props.report.center_name ?? ''} / ${props.report.date ?? ''}`.trim());

const attendanceValue = (row) => Number(row.attendance ?? 1);
const hasScore = (value) => value !== null && value !== undefined && value !== '';
const scoreValue = (value) => (hasScore(value) ? value : '-');

const summary = computed(() => {
    const totals = {
        total: rows.value.length,
        present: 0,
        excused: 0,
        absent: 0,
        frozen: 0,
        perfect: 0,
        scorePercent: null,
    };

    let scoreSum = 0;
    let scoreMax = 0;

    rows.value.forEach((row) => {
        const attendance = attendanceValue(row);

        if (attendance === 2) {
            totals.excused += 1;
        } else if (attendance === 3) {
            totals.absent += 1;
        } else if (attendance === 4) {
            totals.frozen += 1;
        } else {
            totals.present += 1;
        }

        if (row.is_perfect) {
            totals.perfect += 1;
        }

        if (attendance === 1 && hasScore(row.primary_score) && hasScore(row.akhlaqi)) {
            const hasWarud = hasScore(row.warud);
            scoreSum += Number(row.primary_score) + Number(row.akhlaqi) + (hasWarud ? Number(row.warud) : 0);
            scoreMax += hasWarud ? 30 : 20;
        }
    });

    if (scoreMax > 0) {
        totals.scorePercent = Math.round((scoreSum / scoreMax) * 100);
    }

    return totals;
});

const summaryCards = computed(() => [
    { key: 'total', label: 'الطلاب', value: summary.value.total, icon: 'pi pi-users', tone: 'slate' },
    { key: 'present', label: 'حضور', value: summary.value.present, icon: 'pi pi-check-circle', tone: 'green' },
    { key: 'absent', label: 'غياب', value: summary.value.absent, icon: 'pi pi-times-circle', tone: 'red' },
    { key: 'excused', label: 'بعذر', value: summary.value.excused, icon: 'pi pi-info-circle', tone: 'amber' },
    { key: 'frozen', label: 'مجمد', value: summary.value.frozen, icon: 'pi pi-lock', tone: 'cyan' },
    { key: 'score', label: 'متوسط الدرجات', value: summary.value.scorePercent === null ? '-' : `${summary.value.scorePercent}%`, icon: 'pi pi-chart-line', tone: 'blue' },
]);

const attendancePercent = computed(() => {
    if (summary.value.total === 0) {
        return 0;
    }

    return Math.round((summary.value.present / summary.value.total) * 100);
});

const performanceBars = computed(() => [
    {
        key: 'attendance',
        label: 'نسبة الحضور',
        value: attendancePercent.value,
        display: `${attendancePercent.value}%`,
    },
    {
        key: 'scores',
        label: 'متوسط التحصيل',
        value: summary.value.scorePercent ?? 0,
        display: summary.value.scorePercent === null ? '-' : `${summary.value.scorePercent}%`,
    },
]);

const rowToneClass = (row, index) => {
    const attendance = attendanceValue(row);

    if (attendance === 3) {
        return 'is-absent';
    }

    if (attendance === 2) {
        return 'is-excused';
    }

    if (attendance === 4) {
        return 'is-frozen';
    }

    return index % 2 === 0 ? 'is-odd' : 'is-even';
};

const planBadgeClass = (row) => {
    if (Number(row.plan_type_id) === 2) {
        return 'plan-badge--success';
    }

    if (Number(row.plan_type_id) === 3) {
        return 'plan-badge--warning';
    }

    return 'plan-badge--primary';
};

const statusClass = (row) => {
    const attendance = attendanceValue(row);

    if (attendance === 2) {
        return 'status-pill--excused';
    }

    if (attendance === 3) {
        return 'status-pill--absent';
    }

    if (attendance === 4) {
        return 'status-pill--frozen';
    }

    return 'status-pill--present';
};

const statusLabel = (row) => {
    const attendance = attendanceValue(row);

    if (attendance === 2) {
        return 'غائب بعذر';
    }

    if (attendance === 3) {
        return 'غائب';
    }

    if (attendance === 4) {
        return 'مجمد';
    }

    return row.is_perfect ? 'ممتاز' : 'حاضر';
};

const stateMessage = (row) => {
    const attendance = attendanceValue(row);

    if (attendance === 2) {
        return 'غائب بعذر';
    }

    if (attendance === 3) {
        return 'غائب';
    }

    if (attendance === 4) {
        return `مجمد من ${row.freeze_from ?? '-'} إلى ${row.freeze_to ?? '-'}`;
    }

    return '';
};

const noteText = (row) => {
    if (row.note && row.note !== '-') {
        return row.note;
    }

    if (attendanceValue(row) === 4) {
        return row.freeze_reason ?? '';
    }

    return '';
};

const printPage = () => {
    window.print();
};

const copyReportLink = async () => {
    const url = window.location.href;

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
    }

    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 1800);
};
</script>

<template>
    <Head title="تقييمات الطلاب | مشروع الحافظ المتميز">
        <meta name="description" :content="metaDescription">
        <meta property="og:type" content="website">
        <meta property="og:title" content="تقييمات الطلاب | مشروع الحافظ المتميز">
        <meta property="og:description" :content="metaDescription">
        <meta property="og:image" :content="absoluteLogoUrl">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="تقييمات الطلاب | مشروع الحافظ المتميز">
        <meta name="twitter:description" :content="metaDescription">
        <meta name="twitter:image" :content="absoluteLogoUrl">
    </Head>

    <main dir="rtl" class="report-page">
        <div class="report-shell">
            <section class="report-hero" aria-label="بيانات التقرير">
                <div class="report-actions print-hidden">
                    <button type="button" class="icon-button" title="طباعة التقرير" aria-label="طباعة التقرير" @click="printPage">
                        <i class="pi pi-print" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="icon-button"
                        :class="{ 'icon-button--success': copied }"
                        :title="copied ? 'تم نسخ الرابط' : 'نسخ الرابط'"
                        :aria-label="copied ? 'تم نسخ الرابط' : 'نسخ الرابط'"
                        @click="copyReportLink"
                    >
                        <i :class="copied ? 'pi pi-check' : 'pi pi-link'" aria-hidden="true" />
                    </button>
                </div>

                <div class="brand-lockup">
                    <img class="report-logo" :src="logoUrl" alt="Logo">
                    <div>
                        <p class="report-kicker">مشروع الحافظ المتميز</p>
                        <h1>تقييمات الطلاب</h1>
                    </div>
                </div>

                <div class="report-meta">
                    <p class="report-center">{{ report.center_name }}</p>
                    <div class="date-row">
                        <span><i class="pi pi-calendar" aria-hidden="true" />{{ report.date }}</span>
                        <span v-if="report.hijri_date"><i class="pi pi-moon" aria-hidden="true" />{{ report.hijri_date }}</span>
                    </div>
                </div>

                <aside class="performance-panel" aria-label="مؤشرات الأداء">
                    <div
                        v-for="item in performanceBars"
                        :key="item.key"
                        class="performance-row"
                    >
                        <div class="performance-row__label">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.display }}</strong>
                        </div>
                        <div class="performance-track">
                            <span class="performance-fill" :style="{ width: `${item.value}%` }" />
                        </div>
                    </div>
                </aside>
            </section>

            <section class="summary-grid" aria-label="ملخص التقييم">
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="summary-card"
                    :class="`summary-card--${card.tone}`"
                >
                    <i :class="card.icon" aria-hidden="true" />
                    <span>{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <div class="legend-row print-hidden" aria-label="دليل الألوان">
                <span><i class="legend-dot legend-dot--present" />حاضر</span>
                <span><i class="legend-dot legend-dot--excused" />غائب بعذر</span>
                <span><i class="legend-dot legend-dot--absent" />غائب</span>
                <span><i class="legend-dot legend-dot--frozen" />مجمد</span>
            </div>

            <div class="section-heading">
                <div>
                    <p>النتائج التفصيلية</p>
                    <h2>كشف تقييم الطلاب</h2>
                </div>
                <span>{{ rows.length }} طالب</span>
            </div>

            <section class="desktop-table" aria-label="جدول تقييمات الطلاب">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">الاسم</th>
                            <th scope="col">الخطة</th>
                            <th scope="col">الحالة</th>
                            <th scope="col">{{ scoreLabel }}</th>
                            <th scope="col">الورد</th>
                            <th scope="col">الأخلاق</th>
                            <th scope="col">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="report-empty">لا توجد بيانات لهذا التقييم</td>
                        </tr>

                        <tr
                            v-for="(row, index) in rows"
                            :key="row.item_id"
                            class="report-row"
                            :class="rowToneClass(row, index)"
                        >
                            <td class="report-index">
                                <span>{{ row.number }}</span>
                                <i v-if="row.is_perfect" class="pi pi-trophy report-icon" aria-hidden="true" />
                                <i v-else-if="attendanceValue(row) === 4" class="pi pi-lock report-icon" aria-hidden="true" />
                            </td>
                            <td class="report-name">{{ row.full_name }}</td>
                            <td>
                                <span class="plan-badge" :class="planBadgeClass(row)">
                                    {{ row.plan_name }}
                                </span>
                            </td>
                            <td class="report-status">
                                <span class="status-pill" :class="statusClass(row)">
                                    <i v-if="row.is_perfect" class="pi pi-trophy" aria-hidden="true" />
                                    <i v-else-if="attendanceValue(row) === 4" class="pi pi-lock" aria-hidden="true" />
                                    {{ statusLabel(row) }}
                                </span>
                            </td>
                            <template v-if="[2, 3, 4].includes(attendanceValue(row))">
                                <td colspan="3" class="report-state">{{ stateMessage(row) }}</td>
                            </template>
                            <template v-else>
                                <td>
                                    <strong>{{ scoreValue(row.primary_score) }}</strong>
                                    <span v-if="hasScore(row.primary_score)" class="score-max">/ 10</span>
                                </td>
                                <td>
                                    <strong>{{ scoreValue(row.warud) }}</strong>
                                    <span v-if="hasScore(row.warud)" class="score-max">/ 10</span>
                                </td>
                                <td>
                                    <strong>{{ scoreValue(row.akhlaqi) }}</strong>
                                    <span v-if="hasScore(row.akhlaqi)" class="score-max">/ 10</span>
                                </td>
                            </template>
                            <td class="report-note">{{ noteText(row) || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mobile-list" aria-label="قائمة تقييمات الطلاب">
                <p v-if="rows.length === 0" class="mobile-empty">لا توجد بيانات لهذا التقييم</p>

                <article
                    v-for="(row, index) in rows"
                    :key="`mobile-${row.item_id}`"
                    class="student-card"
                    :class="rowToneClass(row, index)"
                >
                    <div class="student-card__header">
                        <div class="student-title">
                            <span class="student-number">{{ row.number }}</span>
                            <h2>{{ row.full_name }}</h2>
                        </div>
                        <span class="status-pill" :class="statusClass(row)">
                            <i v-if="row.is_perfect" class="pi pi-trophy" aria-hidden="true" />
                            <i v-else-if="attendanceValue(row) === 4" class="pi pi-lock" aria-hidden="true" />
                            {{ statusLabel(row) }}
                        </span>
                    </div>

                    <div class="student-plan">
                        <span class="plan-badge" :class="planBadgeClass(row)">
                            {{ row.plan_name }}
                        </span>
                    </div>

                    <div v-if="[2, 3, 4].includes(attendanceValue(row))" class="state-band">
                        {{ stateMessage(row) }}
                    </div>
                    <div v-else class="scores-grid">
                        <div class="score-tile">
                            <span>{{ scoreLabel }}</span>
                            <strong>{{ scoreValue(row.primary_score) }}</strong>
                            <small v-if="hasScore(row.primary_score)">/10</small>
                        </div>
                        <div class="score-tile">
                            <span>الورد</span>
                            <strong>{{ scoreValue(row.warud) }}</strong>
                            <small v-if="hasScore(row.warud)">/10</small>
                        </div>
                        <div class="score-tile">
                            <span>الأخلاق</span>
                            <strong>{{ scoreValue(row.akhlaqi) }}</strong>
                            <small v-if="hasScore(row.akhlaqi)">/10</small>
                        </div>
                    </div>

                    <p v-if="noteText(row)" class="student-note">{{ noteText(row) }}</p>
                </article>
            </section>
        </div>
    </main>
</template>

<style scoped>
:global(body) {
    background: #f3f6fa !important;
}

:global(html.dark body),
:global(.dark body) {
    background: #f3f6fa !important;
}

.report-page {
    min-height: 100vh;
    overflow-x: hidden;
    background: #f3f6fa;
    color: #172033;
    font-family: Cairo, Tajawal, Arial, sans-serif;
    padding: 24px;
}

.report-shell {
    width: min(1180px, 100%);
    margin: 0 auto;
}

.report-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 24px;
    padding: 28px;
    overflow: hidden;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 16px 50px rgba(15, 23, 42, 0.08);
}

.report-hero::before {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 7px;
    background: #016e3d;
}

.report-actions {
    position: absolute;
    inset-inline-end: 22px;
    top: 22px;
    display: flex;
    gap: 10px;
}

.icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border: 1px solid #d7e0ea;
    border-radius: 8px;
    background: #ffffff;
    color: #1f2937;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
    transition: transform 0.16s ease, border-color 0.16s ease, color 0.16s ease;
}

.icon-button:hover {
    transform: translateY(-1px);
    border-color: #016e3d;
    color: #016e3d;
}

.icon-button--success {
    border-color: #047857;
    color: #047857;
}

.brand-lockup {
    display: flex;
    align-items: center;
    gap: 22px;
    padding-inline-end: 110px;
    grid-column: 1 / 2;
}

.report-logo {
    width: 118px;
    height: 118px;
    flex: 0 0 auto;
    object-fit: contain;
}

.report-kicker {
    margin: 0 0 8px;
    color: #016e3d;
    font-size: 0.95rem;
    font-weight: 800;
}

.report-hero h1 {
    margin: 0;
    color: #111827;
    font-size: 3rem;
    font-weight: 900;
    line-height: 1.12;
    letter-spacing: 0;
}

.report-meta {
    display: grid;
    gap: 12px;
    grid-column: 1 / 2;
}

.report-center {
    margin: 0;
    color: #0f3d6e;
    font-size: 2rem;
    font-weight: 900;
    line-height: 1.35;
}

.date-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.date-row span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    padding: 8px 12px;
    font-weight: 800;
}

.date-row i {
    color: #016e3d;
}

.performance-panel {
    grid-column: 2 / 3;
    grid-row: 1 / span 2;
    align-self: end;
    display: grid;
    gap: 18px;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 18px;
}

.performance-row {
    display: grid;
    gap: 10px;
}

.performance-row__label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #475569;
    font-size: 0.9rem;
    font-weight: 900;
}

.performance-row__label strong {
    color: #0f3d6e;
    font-size: 1.25rem;
    font-weight: 900;
}

.performance-track {
    position: relative;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #e2e8f0;
}

.performance-fill {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    border-radius: inherit;
    background: #016e3d;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
}

.summary-card {
    display: grid;
    gap: 7px;
    min-height: 112px;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.summary-card i {
    font-size: 1.15rem;
}

.summary-card span {
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 800;
}

.summary-card strong {
    color: #111827;
    font-size: 1.55rem;
    font-weight: 900;
    line-height: 1;
}

.summary-card--slate i {
    color: #475569;
}

.summary-card--green i {
    color: #047857;
}

.summary-card--red i {
    color: #dc2626;
}

.summary-card--amber i {
    color: #d97706;
}

.summary-card--cyan i {
    color: #0891b2;
}

.summary-card--blue i {
    color: #2563eb;
}

.legend-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
    color: #475569;
    font-size: 0.92rem;
    font-weight: 800;
}

.legend-row span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
}

.legend-dot--present {
    background: #22c55e;
}

.legend-dot--excused {
    background: #f59e0b;
}

.legend-dot--absent {
    background: #ef4444;
}

.legend-dot--frozen {
    background: #06b6d4;
}

.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
    margin-top: 22px;
}

.section-heading p,
.section-heading h2 {
    margin: 0;
}

.section-heading p {
    color: #016e3d;
    font-size: 0.9rem;
    font-weight: 900;
}

.section-heading h2 {
    color: #111827;
    font-size: 1.45rem;
    font-weight: 900;
}

.section-heading > span {
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    color: #334155;
    padding: 8px 12px;
    font-weight: 900;
}

.desktop-table {
    margin-top: 12px;
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 16px 45px rgba(15, 23, 42, 0.08);
}

.report-table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    table-layout: auto;
    text-align: center;
    font-size: 0.98rem;
}

.report-table thead {
    position: sticky;
    top: 0;
    z-index: 1;
}

.report-table th {
    background: #182233;
    color: #fbbf24;
    font-weight: 900;
    padding: 15px 12px;
    border-inline-end: 1px solid rgba(255, 255, 255, 0.08);
}

.report-table td {
    color: #172033;
    font-weight: 800;
    padding: 14px 12px;
    border-bottom: 1px solid #e5edf5;
    vertical-align: middle;
}

.report-row.is-odd {
    background: #fbfdff;
}

.report-row.is-even {
    background: #ffffff;
}

.report-row.is-absent {
    background: #fee2e2;
}

.report-row.is-excused {
    background: #ffedd5;
}

.report-row.is-frozen {
    background: #cffafe;
}

.report-index {
    width: 72px;
    white-space: nowrap;
}

.report-icon {
    margin-inline-start: 7px;
    color: #0f3d6e;
    font-size: 1.05rem;
}

.report-name {
    min-width: 240px;
    text-align: start;
}

.report-status {
    min-width: 118px;
}

.report-state {
    color: #334155;
    font-size: 1rem;
}

.report-note {
    min-width: 220px;
    max-width: 360px;
    color: #475569;
    white-space: normal;
}

.report-empty {
    background: #ffffff;
    color: #64748b;
    padding: 30px 12px;
}

.plan-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 88px;
    border-radius: 8px;
    padding: 7px 12px;
    font-size: 0.85rem;
    font-weight: 900;
    line-height: 1.2;
}

.plan-badge--primary {
    color: #1d4ed8;
    background: #dbeafe;
}

.plan-badge--success {
    color: #047857;
    background: #d1fae5;
}

.plan-badge--warning {
    color: #a16207;
    background: #fef3c7;
}

.score-max {
    margin-inline-start: 4px;
    color: #64748b;
    font-weight: 700;
}

.mobile-list {
    display: none;
}

.mobile-empty {
    margin: 18px 0 0;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #64748b;
    padding: 22px;
    text-align: center;
    font-weight: 800;
}

.student-card {
    position: relative;
    display: grid;
    gap: 14px;
    margin-top: 12px;
    overflow: hidden;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.07);
}

.student-card::before {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: #22c55e;
}

.student-card.is-absent::before {
    background: #ef4444;
}

.student-card.is-excused::before {
    background: #f59e0b;
}

.student-card.is-frozen::before {
    background: #06b6d4;
}

.student-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.student-title {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 9px;
    align-items: start;
    min-width: 0;
}

.student-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: #eef6ff;
    color: #0f3d6e;
    font-size: 0.86rem;
    font-weight: 900;
}

.student-title h2 {
    margin: 0;
    color: #172033;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.student-plan {
    display: flex;
    justify-content: flex-start;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    flex: 0 0 auto;
    border-radius: 8px;
    padding: 6px 9px;
    font-size: 0.78rem;
    font-weight: 900;
    white-space: nowrap;
}

.status-pill--present {
    background: #dcfce7;
    color: #166534;
}

.status-pill--excused {
    background: #ffedd5;
    color: #9a3412;
}

.status-pill--absent {
    background: #fee2e2;
    color: #991b1b;
}

.status-pill--frozen {
    background: #cffafe;
    color: #155e75;
}

.scores-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.score-tile {
    min-width: 0;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px 8px;
    text-align: center;
}

.score-tile span,
.score-tile small {
    display: block;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 800;
}

.score-tile strong {
    display: inline-block;
    margin-top: 4px;
    color: #111827;
    font-size: 1.35rem;
    font-weight: 900;
    line-height: 1;
}

.state-band {
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    padding: 12px;
    text-align: center;
    font-weight: 900;
}

.student-note {
    margin: 0;
    border-top: 1px solid #e2e8f0;
    color: #475569;
    padding-top: 12px;
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.65;
}

@media (max-width: 1050px) {
    .report-hero {
        grid-template-columns: 1fr;
    }

    .brand-lockup,
    .report-meta,
    .performance-panel {
        grid-column: auto;
        grid-row: auto;
    }

    .performance-panel {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .report-page {
        padding: 12px;
    }

    .report-hero {
        gap: 18px;
        padding: 18px;
        padding-top: 72px;
    }

    .report-actions {
        inset-inline-end: 14px;
        top: 14px;
    }

    .brand-lockup {
        display: grid;
        justify-items: center;
        gap: 12px;
        padding-inline-end: 0;
        text-align: center;
    }

    .performance-panel {
        grid-template-columns: 1fr;
        padding: 14px;
    }

    .report-logo {
        width: 92px;
        height: 92px;
    }

    .report-kicker {
        font-size: 0.85rem;
    }

    .report-hero h1 {
        font-size: 2rem;
    }

    .report-meta {
        text-align: center;
    }

    .report-center {
        font-size: 1.25rem;
    }

    .date-row {
        justify-content: center;
    }

    .date-row span {
        width: 100%;
        justify-content: center;
        font-size: 0.88rem;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-card {
        min-height: 96px;
        padding: 12px;
    }

    .section-heading {
        align-items: stretch;
        display: grid;
    }

    .section-heading > span {
        justify-self: start;
    }

    .desktop-table {
        display: none;
    }

    .mobile-list {
        display: block;
        margin-top: 18px;
    }
}

@media (max-width: 420px) {
    .summary-grid {
        gap: 8px;
    }

    .summary-card strong {
        font-size: 1.35rem;
    }

    .student-card__header {
        display: grid;
    }

    .status-pill {
        justify-self: start;
    }
}

@media print {
    :global(body) {
        background: #ffffff !important;
    }

    .report-page {
        background: #ffffff;
        padding: 0;
    }

    .report-shell {
        width: 100%;
    }

    .print-hidden,
    .legend-row,
    .performance-panel,
    .mobile-list {
        display: none !important;
    }

    .report-hero,
    .summary-card,
    .desktop-table {
        box-shadow: none;
    }

    .report-hero {
        border: 0;
        border-radius: 0;
        padding: 18px 0;
    }

    .summary-grid {
        grid-template-columns: repeat(6, 1fr);
        margin: 8px 0 12px;
    }

    .summary-card {
        min-height: 72px;
        padding: 8px;
    }

    .desktop-table {
        display: block;
        border: 0;
        border-radius: 0;
        margin-top: 10px;
    }

    .report-table {
        min-width: 0;
        font-size: 0.78rem;
    }

    .report-table th,
    .report-table td {
        padding: 7px 6px;
    }
}
</style>
