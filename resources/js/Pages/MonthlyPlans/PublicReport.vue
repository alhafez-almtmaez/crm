<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const copied = shallowRef(false);
const logoUrl = computed(() => '/media/logos/logo.png');
const monthNames = [
    '',
    'كانون الثاني',
    'شباط',
    'آذار',
    'نيسان',
    'أيار',
    'حزيران',
    'تموز',
    'آب',
    'أيلول',
    'تشرين الأول',
    'تشرين الثاني',
    'كانون الأول',
];

const monthlyPlan = computed(() => props.report.monthly_plan ?? {});
const dates = computed(() => props.report.dates ?? []);
const planRows = computed(() => (props.report.plans ?? []).map((plan) => ({
    ...plan,
    student_name: String(plan.student_name ?? '').trim() || 'بدون اسم',
    dayMap: Object.fromEntries((plan.days ?? []).map((day) => [day.date, day])),
})));
const monthLabel = computed(() => monthNames[Number(monthlyPlan.value.month)] ?? monthlyPlan.value.month);
const monthDisplay = computed(() => `${monthLabel.value} (${Number(monthlyPlan.value.month) || '-'})`);
const title = computed(() => `الخطة الشهرية - ${monthlyPlan.value.group_name ?? ''}`);

const cellItems = (plan, date) => plan.dayMap?.[date]?.items ?? [];
const shortDate = (date) => {
    const [, month, day] = String(date).split('-').map((segment) => Number(segment));

    return month && day ? `${day}/${month}` : date;
};

const englishNumber = (value) => Number(value ?? 0).toLocaleString('en-US');

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

const printPage = () => {
    window.print();
};
</script>

<template>
    <Head :title="title" />

    <main dir="rtl" class="monthly-report-page">
        <section class="report-hero">
            <div class="brand-lockup">
                <img class="report-logo" :src="logoUrl" alt="Logo">
                <div>
                    <p>مشروع الحافظ المتميز</p>
                    <h1>الخطة الشهرية</h1>
                </div>
            </div>

            <div class="report-actions print-hidden">
                <button type="button" class="icon-button" title="طباعة الصفحة" aria-label="طباعة الصفحة" @click="printPage">
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

            <div class="report-meta">
                <div>
                    <span>المركز</span>
                    <strong>{{ monthlyPlan.center_name || '-' }}</strong>
                </div>
                <div>
                    <span>المجموعة</span>
                    <strong>{{ monthlyPlan.group_name || '-' }}</strong>
                </div>
                <div>
                    <span>الشهر</span>
                    <strong>{{ monthDisplay }} {{ monthlyPlan.year }}</strong>
                </div>
                <div>
                    <span>تاريخ الإنشاء</span>
                    <strong>{{ monthlyPlan.generated_at || '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="summary-grid" aria-label="ملخص الخطة الشهرية">
            <article>
                <span>عدد الطلاب</span>
                <strong>{{ englishNumber(monthlyPlan.students_count) }}</strong>
            </article>
            <article>
                <span>أيام الخطة</span>
                <strong>{{ englishNumber(dates.length) }}</strong>
            </article>
            <article>
                <span>عناصر الخطة</span>
                <strong>{{ englishNumber(monthlyPlan.generated_items_count) }}</strong>
            </article>
        </section>

        <section class="plan-section" aria-label="جدول الخطة الشهرية">
            <div class="section-heading">
                <div>
                    <p>توزيع الواجبات</p>
                    <h2>خطة الطلاب الشهرية</h2>
                </div>
                <span>{{ englishNumber(planRows.length) }} طالب</span>
            </div>

            <div v-if="planRows.length === 0" class="empty-state">
                لا توجد بيانات خطة محفوظة لهذا الرابط.
            </div>

            <div v-else class="plan-table-wrap">
                <table class="plan-table">
                    <thead>
                        <tr>
                            <th class="student-header" scope="col">الطالب</th>
                            <th
                                v-for="date in dates"
                                :key="date.date"
                                scope="col"
                            >
                                <span>{{ shortDate(date.date) }}</span>
                                <small>{{ date.day_label }}</small>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="plan in planRows" :key="plan.id">
                            <th class="student-cell" scope="row">
                                <strong>{{ plan.student_name }}</strong>
                                <span>{{ plan.plan_name || '-' }}</span>
                            </th>
                            <td
                                v-for="date in dates"
                                :key="`${plan.id}-${date.date}`"
                            >
                                <div v-if="cellItems(plan, date.date).length" class="items-stack">
                                    <span
                                        v-for="item in cellItems(plan, date.date)"
                                        :key="item.id"
                                        class="plan-item"
                                    >
                                        {{ item.name }}
                                    </span>
                                </div>
                                <span v-else class="empty-cell">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
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

.monthly-report-page {
    min-height: 100vh;
    overflow-x: hidden;
    background: #f3f6fa;
    color: #172033;
    font-family: Cairo, Tajawal, Arial, sans-serif;
    padding: 24px;
}

.report-hero,
.plan-section,
.summary-grid article {
    width: min(1180px, 100%);
    margin-inline: auto;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 16px 50px rgba(15, 23, 42, 0.08);
}

.report-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    padding: 22px;
    overflow: hidden;
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

.brand-lockup {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}

.report-logo {
    width: 82px;
    height: 82px;
    flex: 0 0 auto;
    object-fit: contain;
}

.brand-lockup p,
.section-heading p {
    margin: 0 0 6px;
    color: #016e3d;
    font-size: 0.84rem;
    font-weight: 900;
}

.brand-lockup h1,
.section-heading h2 {
    margin: 0;
    color: #111827;
    font-weight: 900;
    line-height: 1.25;
    letter-spacing: 0;
}

.brand-lockup h1 {
    font-size: 2.05rem;
}

.report-actions {
    display: flex;
    gap: 10px;
    align-self: start;
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
}

.icon-button--success {
    border-color: #047857;
    color: #047857;
}

.report-meta {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.report-meta div {
    display: grid;
    gap: 6px;
    min-width: 0;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 11px 12px;
}

.report-meta span,
.summary-grid span,
.student-cell span,
.empty-cell,
.section-heading > span {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 800;
}

.report-meta strong,
.summary-grid strong {
    overflow-wrap: anywhere;
    color: #0f3d6e;
    font-weight: 900;
    line-height: 1.45;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    width: min(1180px, 100%);
    margin: 18px auto 0;
}

.summary-grid article {
    display: grid;
    gap: 6px;
    min-height: 96px;
    margin: 0;
    padding: 14px;
}

.summary-grid strong {
    font-size: 1.45rem;
}

.plan-section {
    margin-top: 18px;
    padding: 18px;
}

.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
}

.plan-table-wrap {
    margin-top: 14px;
    overflow-x: auto;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
}

.plan-table {
    width: 100%;
    min-width: 900px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    font-size: 0.86rem;
}

.plan-table th {
    background: #0f3d6e;
    color: #ffffff;
    padding: 12px 10px;
    text-align: start;
    font-weight: 900;
}

.plan-table th small {
    display: block;
    margin-top: 4px;
    color: #dbeafe;
    font-size: 0.72rem;
    font-weight: 800;
}

.student-header,
.student-cell {
    position: sticky;
    right: 0;
    z-index: 2;
    width: 220px;
}

.student-cell {
    background: #ffffff !important;
    color: #111827;
}

.student-cell strong,
.student-cell span {
    display: block;
}

.student-cell span {
    margin-top: 5px;
}

.plan-table td,
.student-cell {
    vertical-align: top;
    border-bottom: 1px solid #e6edf5;
    padding: 10px;
}

.plan-table tbody tr:nth-child(even) td,
.plan-table tbody tr:nth-child(even) .student-cell {
    background: #f8fafc !important;
}

.items-stack {
    display: grid;
    gap: 6px;
}

.plan-item {
    display: block;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    color: #111827;
    padding: 7px 8px;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.empty-state {
    margin-top: 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #64748b;
    padding: 22px;
    text-align: center;
    font-weight: 900;
}

.students-list {
    margin-top: 14px;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 14px;
}

.students-list__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    color: #0f3d6e;
    font-weight: 900;
}

.students-list ol {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style-position: inside;
}

.students-list li {
    min-width: 0;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 10px 11px;
    color: #111827;
    font-weight: 900;
}

.students-list li::marker {
    color: #016e3d;
    font-weight: 900;
}

.students-list li strong,
.students-list li span {
    overflow-wrap: anywhere;
}

.students-list li span {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
}

@media (max-width: 760px) {
    .monthly-report-page {
        padding: 14px;
    }

    .report-hero,
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .report-actions {
        justify-self: end;
    }

    .report-meta {
        grid-template-columns: 1fr;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }
}

@media print {
    :global(body),
    .monthly-report-page {
        background: #ffffff !important;
    }

    .monthly-report-page {
        padding: 0;
    }

    .print-hidden {
        display: none !important;
    }

    .report-hero,
    .plan-section,
    .summary-grid article {
        box-shadow: none;
    }
}
</style>
