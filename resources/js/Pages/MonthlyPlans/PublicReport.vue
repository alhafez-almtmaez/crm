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
const studentSearch = shallowRef('');
const expandedMobilePlanIds = shallowRef(new Set());
const mobileInitialDaysCount = 2;
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
const normalizeSearch = (value) => String(value ?? '')
    .normalize('NFKD')
    .replace(/[\u064B-\u065F\u0670]/g, '')
    .replace(/[إأآا]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ؤ/g, 'و')
    .replace(/ئ/g, 'ي')
    .replace(/ة/g, 'ه')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
const englishNumber = (value) => Number(value ?? 0).toLocaleString('en-US');
const normalizedStudentSearch = computed(() => normalizeSearch(studentSearch.value));
const filteredPlanRows = computed(() => {
    if (!normalizedStudentSearch.value) {
        return planRows.value;
    }

    return planRows.value.filter((plan) => normalizeSearch(plan.student_name).includes(normalizedStudentSearch.value));
});
const tableStudentsCount = computed(() => {
    if (!normalizedStudentSearch.value) {
        return `${englishNumber(planRows.value.length)} طالب`;
    }

    return `${englishNumber(filteredPlanRows.value.length)} من ${englishNumber(planRows.value.length)} طالب`;
});
const monthLabel = computed(() => monthNames[Number(monthlyPlan.value.month)] ?? monthlyPlan.value.month);
const monthDisplay = computed(() => `${monthLabel.value} (${Number(monthlyPlan.value.month) || '-'})`);
const title = computed(() => `الخطة الشهرية ${monthlyPlan.value.month}/${monthlyPlan.value.year} - ${monthlyPlan.value.group_name ?? ''}`);

const cellItems = (plan, date) => plan.dayMap?.[date]?.items ?? [];
const shortDate = (date) => {
    const [, month, day] = String(date).split('-').map((segment) => Number(segment));

    return month && day ? `${day}/${month}` : date;
};
const isMobilePlanExpanded = (plan) => expandedMobilePlanIds.value.has(plan.id);
const mobileDatesForPlan = (plan) => (isMobilePlanExpanded(plan) ? dates.value : dates.value.slice(0, mobileInitialDaysCount));
const remainingMobileDatesCount = computed(() => Math.max(dates.value.length - mobileInitialDaysCount, 0));
const toggleMobilePlan = (plan) => {
    const next = new Set(expandedMobilePlanIds.value);

    if (next.has(plan.id)) {
        next.delete(plan.id);
    } else {
        next.add(plan.id);
    }

    expandedMobilePlanIds.value = next;
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

const printPage = () => {
    window.print();
};

const clearStudentSearch = () => {
    studentSearch.value = '';
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
                <i class="pi pi-users" aria-hidden="true" />
                <div>
                    <span>عدد الطلاب</span>
                    <strong>{{ englishNumber(monthlyPlan.students_count) }}</strong>
                </div>
            </article>
            <article>
                <i class="pi pi-calendar" aria-hidden="true" />
                <div>
                    <span>أيام الخطة</span>
                    <strong>{{ englishNumber(dates.length) }}</strong>
                </div>
            </article>
            <article>
                <i class="pi pi-list-check" aria-hidden="true" />
                <div>
                    <span>عناصر الخطة</span>
                    <strong>{{ englishNumber(monthlyPlan.generated_items_count) }}</strong>
                </div>
            </article>
        </section>

        <section class="plan-section" aria-label="جدول الخطة الشهرية">
            <div class="section-heading">
                <div>
                    <p>توزيع الواجبات</p>
                    <h2>خطة الطلاب الشهرية</h2>
                </div>
                <span>{{ tableStudentsCount }}</span>
            </div>

            <div v-if="planRows.length > 0" class="report-search print-hidden">
                <label for="monthly-plan-student-search">بحث باسم الطالب</label>
                <div class="search-field">
                    <i class="pi pi-search" aria-hidden="true" />
                    <input
                        id="monthly-plan-student-search"
                        v-model="studentSearch"
                        type="search"
                        autocomplete="off"
                        inputmode="search"
                        placeholder="اكتب اسم الطالب"
                    >
                    <button
                        v-if="studentSearch"
                        type="button"
                        class="search-clear"
                        title="مسح البحث"
                        aria-label="مسح البحث"
                        @click="clearStudentSearch"
                    >
                        <i class="pi pi-times" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div v-if="planRows.length === 0" class="empty-state">
                لا توجد بيانات خطة محفوظة لهذا الرابط.
            </div>
            <div v-else-if="filteredPlanRows.length === 0" class="empty-state">
                لا توجد نتائج لهذا الاسم.
            </div>

            <div v-else>
                <div class="plan-table-wrap desktop-plan-table">
                    <table class="plan-table">
                        <thead>
                            <tr>
                                <th class="student-header" scope="col">الطالب والخطة</th>
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
                            <tr v-for="plan in filteredPlanRows" :key="plan.id">
                                <th class="student-cell" scope="row">
                                    <strong>{{ plan.student_name }}</strong>
                                    <span class="plan-pill">{{ plan.plan_name || '-' }}</span>
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

                <div class="mobile-plan-list" aria-label="خطة الطلاب الشهرية للموبايل">
                    <article v-for="plan in filteredPlanRows" :key="`mobile-${plan.id}`" class="mobile-student-card">
                        <header class="mobile-student-header">
                            <div>
                                <strong>{{ plan.student_name }}</strong>
                                <span class="plan-pill">{{ plan.plan_name || '-' }}</span>
                            </div>
                            <span>{{ englishNumber(dates.length) }} أيام</span>
                        </header>

                        <div class="mobile-days-list">
                            <section
                                v-for="date in mobileDatesForPlan(plan)"
                                :key="`${plan.id}-${date.date}-mobile`"
                                class="mobile-day-card"
                            >
                                <header class="mobile-day-header">
                                    <strong>{{ shortDate(date.date) }}</strong>
                                    <span>{{ date.day_label }}</span>
                                </header>

                                <div v-if="cellItems(plan, date.date).length" class="mobile-items-stack">
                                    <span
                                        v-for="item in cellItems(plan, date.date)"
                                        :key="`${item.id}-mobile`"
                                        class="plan-item"
                                    >
                                        {{ item.name }}
                                    </span>
                                </div>
                                <span v-else class="mobile-empty-cell">لا يوجد واجب</span>
                            </section>
                        </div>

                        <button
                            v-if="remainingMobileDatesCount > 0"
                            type="button"
                            class="mobile-more-button"
                            @click="toggleMobilePlan(plan)"
                        >
                            <span>{{ isMobilePlanExpanded(plan) ? 'عرض أقل' : `عرض المزيد (${englishNumber(remainingMobileDatesCount)})` }}</span>
                            <i :class="isMobilePlanExpanded(plan) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" aria-hidden="true" />
                        </button>
                    </article>
                </div>
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
    background: #eef3f8;
    color: #172033;
    font-family: Cairo, Tajawal, Arial, sans-serif;
    padding: 24px;
}

.report-hero,
.plan-section {
    width: min(1760px, 100%);
    margin-inline: auto;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.07);
}

.report-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    padding: 22px;
    background:
        linear-gradient(135deg, rgba(1, 110, 61, 0.07), transparent 42%),
        linear-gradient(315deg, rgba(15, 61, 110, 0.08), transparent 46%),
        #ffffff;
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
    border-inline-start: 4px solid #0f3d6e;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.78);
    padding: 11px 12px;
}

.report-meta span,
.summary-grid span,
.student-cell > span,
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
    gap: 14px;
    width: min(1760px, 100%);
    margin: 18px auto 0;
}

.summary-grid article {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 14px;
    min-height: 108px;
    margin: 0;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
    padding: 18px 20px;
}

.summary-grid article::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: 5px;
}

.summary-grid article:nth-child(1)::before {
    background: #016e3d;
}

.summary-grid article:nth-child(2)::before {
    background: #0f3d6e;
}

.summary-grid article:nth-child(3)::before {
    background: #b45309;
}

.summary-grid article > i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 8px;
    font-size: 1.18rem;
}

.summary-grid article:nth-child(1) > i {
    background: #e8f5ef;
    color: #016e3d;
}

.summary-grid article:nth-child(2) > i {
    background: #eff6ff;
    color: #0f3d6e;
}

.summary-grid article:nth-child(3) > i {
    background: #fff7ed;
    color: #b45309;
}

.summary-grid strong {
    display: block;
    margin-top: 2px;
    color: #0f172a;
    font-size: 1.8rem;
    font-weight: 950;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
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

.section-heading > span {
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 8px 10px;
    white-space: nowrap;
}

.report-search {
    display: grid;
    grid-template-columns: minmax(220px, 360px);
    gap: 8px;
    margin-top: 14px;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 12px;
}

.report-search label {
    color: #334155;
    font-size: 0.82rem;
    font-weight: 900;
}

.search-field {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.search-field > i {
    position: absolute;
    inset-inline-start: 12px;
    color: #64748b;
    font-size: 0.9rem;
    pointer-events: none;
}

.search-field input {
    width: 100%;
    height: 42px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #111827;
    font: inherit;
    font-size: 0.9rem;
    font-weight: 800;
    outline: none;
    padding-block: 0;
    padding-inline: 42px 38px;
}

.search-field input:focus {
    border-color: #0f3d6e;
    box-shadow: 0 0 0 3px rgba(15, 61, 110, 0.12);
}

.search-field input::placeholder {
    color: #94a3b8;
}

.search-clear {
    position: absolute;
    inset-inline-end: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: #eef2f7;
    color: #334155;
}

.search-clear:hover,
.search-clear:focus-visible {
    background: #dbe5ef;
    color: #0f3d6e;
    outline: none;
}

.plan-table-wrap {
    margin-top: 14px;
    overflow-x: auto;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
}

.plan-table {
    width: 100%;
    min-width: 1080px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    font-size: 0.86rem;
}

.plan-table th {
    background: #103f68;
    color: #ffffff;
    padding: 13px 10px;
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
    width: 240px;
    min-width: 240px;
    max-width: 240px;
}

.student-cell {
    z-index: 3;
    background: #fbfdff !important;
    box-shadow: -1px 0 0 #dfe7ef;
}

.student-cell strong {
    display: block;
    color: #111827;
    font-size: 0.92rem;
    font-weight: 900;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.plan-pill {
    display: inline-block;
    max-width: 100%;
    margin-top: 9px;
    border: 1px solid #b7d8c8;
    border-radius: 8px;
    background: #edf8f2;
    color: #016e3d;
    padding: 6px 10px;
    font-size: 0.78rem;
    font-weight: 900;
    line-height: 1.35;
    overflow-wrap: anywhere;
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

.plan-table tbody tr:hover td,
.plan-table tbody tr:hover .student-cell {
    background: #f3f8ff !important;
}

.mobile-plan-list {
    display: none;
}

.items-stack {
    display: grid;
    gap: 6px;
}

.plan-item {
    display: block;
    border: 1px solid #d7e2ee;
    border-inline-start: 4px solid #016e3d;
    border-radius: 8px;
    background: #fbfdff;
    color: #111827;
    padding: 8px 9px;
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

@media (max-width: 760px) {
    .monthly-report-page {
        padding: 10px;
    }

    .report-hero,
    .summary-grid,
    .report-meta {
        grid-template-columns: 1fr;
    }

    .report-hero {
        gap: 14px;
        padding: 16px 14px;
    }

    .report-hero::before {
        width: 5px;
    }

    .brand-lockup {
        align-items: flex-start;
        gap: 10px;
    }

    .report-logo {
        width: 58px;
        height: 58px;
    }

    .brand-lockup p {
        margin-bottom: 3px;
        font-size: 0.76rem;
    }

    .brand-lockup h1 {
        font-size: 1.35rem;
    }

    .report-actions {
        justify-self: end;
    }

    .report-meta div {
        padding: 10px 11px;
    }

    .summary-grid {
        gap: 10px;
        margin-top: 10px;
    }

    .summary-grid article {
        grid-template-columns: auto minmax(0, 1fr);
        min-height: 78px;
        padding: 12px;
    }

    .summary-grid article > i {
        width: 38px;
        height: 38px;
        font-size: 0.98rem;
    }

    .summary-grid strong {
        font-size: 1.35rem;
    }

    .plan-section {
        margin-top: 10px;
        padding: 12px;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }

    .section-heading h2 {
        font-size: 1.15rem;
    }

    .section-heading > span {
        white-space: normal;
    }

    .report-search {
        grid-template-columns: 1fr;
        width: 100%;
        margin-top: 12px;
        padding: 10px;
    }

    .desktop-plan-table {
        display: none;
    }

    .mobile-plan-list {
        display: grid;
        gap: 12px;
        margin-top: 12px;
    }

    .mobile-student-card {
        overflow: hidden;
        border: 1px solid #dbe5ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
    }

    .mobile-student-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        border-bottom: 1px solid #e6edf5;
        background: #fbfdff;
        padding: 12px;
    }

    .mobile-student-header strong {
        display: block;
        color: #0f172a;
        font-size: 0.98rem;
        font-weight: 950;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .mobile-student-header > span {
        flex: 0 0 auto;
        border: 1px solid #dbe5ef;
        border-radius: 8px;
        background: #f8fafc;
        color: #64748b;
        padding: 6px 8px;
        font-size: 0.74rem;
        font-weight: 900;
    }

    .mobile-days-list {
        display: grid;
        gap: 10px;
        padding: 10px;
    }

    .mobile-day-card {
        border: 1px solid #dfe7ef;
        border-radius: 8px;
        background: #ffffff;
    }

    .mobile-day-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border-bottom: 1px solid #edf2f7;
        background: #f8fafc;
        padding: 9px 10px;
    }

    .mobile-day-header strong {
        color: #103f68;
        font-size: 0.86rem;
        font-weight: 950;
    }

    .mobile-day-header span {
        color: #64748b;
        font-size: 0.76rem;
        font-weight: 900;
    }

    .mobile-items-stack {
        display: grid;
        gap: 7px;
        padding: 9px;
    }

    .mobile-empty-cell {
        display: block;
        padding: 11px 10px;
        color: #94a3b8;
        font-size: 0.8rem;
        font-weight: 900;
        text-align: center;
    }

    .mobile-more-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: calc(100% - 20px);
        min-height: 42px;
        margin: 0 10px 10px;
        border: 1px solid #b7d8c8;
        border-radius: 8px;
        background: #edf8f2;
        color: #016e3d;
        font: inherit;
        font-size: 0.84rem;
        font-weight: 950;
    }

    .mobile-more-button:focus-visible {
        outline: 3px solid rgba(1, 110, 61, 0.18);
        outline-offset: 2px;
    }

    .plan-pill {
        margin-top: 7px;
        padding: 5px 8px;
        font-size: 0.74rem;
    }

    .plan-item {
        padding: 8px;
        font-size: 0.8rem;
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

    .mobile-plan-list {
        display: none !important;
    }

    .desktop-plan-table {
        display: block !important;
    }

    .report-hero,
    .plan-section,
    .summary-grid article {
        box-shadow: none;
    }
}
</style>
