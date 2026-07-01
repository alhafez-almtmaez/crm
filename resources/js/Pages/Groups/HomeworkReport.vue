<script setup>
import { computed, shallowRef } from 'vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const TASK_PREVIEW_COUNT = 3;

const copied = shallowRef(false);
const expandedTaskRows = shallowRef(new Set());
const rows = computed(() => (props.report.rows ?? []).map((row) => {
    const tasks = row.tasks ?? [];

    return {
        ...row,
        tasks,
        previewTasks: tasks.slice(0, TASK_PREVIEW_COUNT),
        hiddenTasks: tasks.slice(TASK_PREVIEW_COUNT),
    };
}));
const logoUrl = computed(() => '/media/logos/logo.png');
const summary = computed(() => props.report.summary ?? {});
const totalTasks = computed(() => Number(summary.value.tasks_count ?? 0));
const studentsCount = computed(() => Number(summary.value.students_count ?? rows.value.length));
const pointsRankingUrl = computed(() => props.report.points_ranking_url ?? null);

const summaryCards = computed(() => [
    {
        key: 'students',
        label: 'الطلاب',
        value: studentsCount.value,
        icon: 'pi pi-users',
    },
    {
        key: 'tasks',
        label: 'واجبات المرة القادمة',
        value: totalTasks.value,
        icon: 'pi pi-list-check',
    },
    {
        key: 'group',
        label: 'المجموعة',
        value: props.report.group_name ?? '-',
        icon: 'pi pi-sitemap',
    },
]);

const rowToneClass = (index) => (index % 2 === 0 ? 'is-odd' : 'is-even');

const currentPointLabel = (row) => row.current_plan_point_name || 'لم يبدأ بعد';
const englishNumber = (value) => Number(value ?? 0).toLocaleString('en-US');
const pointsBalanceLabel = (row) => englishNumber(row.points_balance);
const rowKey = (row) => String(row.student_id ?? row.number);
const isTasksExpanded = (row) => expandedTaskRows.value.has(rowKey(row));
const taskToggleLabel = (row) => (
    isTasksExpanded(row)
        ? 'إخفاء الباقي'
        : `عرض ${englishNumber(row.hiddenTasks.length)} واجبات إضافية`
);

const toggleTasks = (row) => {
    const key = rowKey(row);
    const nextRows = new Set(expandedTaskRows.value);

    if (nextRows.has(key)) {
        nextRows.delete(key);
    } else {
        nextRows.add(key);
    }

    expandedTaskRows.value = nextRows;
};

const taskDetails = (task) => [
    task.surah_name,
    task.part_name,
    task.three_parts,
].filter(Boolean).join(' / ');

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
    <main dir="rtl" class="homework-report-page">
        <div class="report-shell">
            <section class="report-hero" aria-label="بيانات التقرير">
                <div class="brand-lockup">
                    <img class="report-logo" :src="logoUrl" alt="Logo">
                    <div>
                        <p class="report-kicker">مشروع الحافظ المتميز</p>
                        <h1>واجبات المرة القادمة</h1>
                    </div>
                </div>

                <div class="report-actions print-hidden">
                    <a
                        v-if="pointsRankingUrl"
                        class="report-link-button"
                        :href="pointsRankingUrl"
                        title="ترتيب الطلاب حسب النقاط"
                        aria-label="ترتيب الطلاب حسب النقاط"
                    >
                        <i class="pi pi-trophy" aria-hidden="true" />
                        <span>ترتيب النقاط</span>
                    </a>
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
                        <span class="meta-label">المركز</span>
                        <strong>{{ report.center_name }}</strong>
                    </div>
                    <div>
                        <span class="meta-label">المجموعة</span>
                        <strong>{{ report.group_name }}</strong>
                    </div>
                    <div>
                        <span class="meta-label">تاريخ العرض</span>
                        <strong>{{ report.generated_at }}</strong>
                    </div>
                </div>
            </section>

            <section class="summary-grid" aria-label="ملخص واجبات المرة القادمة">
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="summary-card"
                >
                    <i :class="card.icon" aria-hidden="true" />
                    <span>{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <div class="section-heading">
                <div>
                    <p>متابعة الطلاب</p>
                    <h2>المطلوب للمرة القادمة لكل طالب</h2>
                </div>
                <span>{{ rows.length }} طالب</span>
            </div>

            <section class="desktop-table" aria-label="جدول واجبات المرة القادمة">
                <table class="report-table">
                    <colgroup>
                        <col class="number-col">
                        <col class="student-col">
                        <col class="plan-col">
                        <col class="progress-col">
                        <col class="tasks-col">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">اسم الطالب</th>
                            <th scope="col">الخطة</th>
                            <th scope="col">آخر إنجاز</th>
                            <th scope="col">واجبات المرة القادمة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="report-empty">لا يوجد طلاب فعالين في هذه المجموعة</td>
                        </tr>

                        <tr
                            v-for="(row, index) in rows"
                            :key="row.student_id"
                            class="report-row"
                            :class="rowToneClass(index)"
                        >
                            <td class="report-index">{{ row.number }}</td>
                            <td class="report-name">
                                <div class="student-identity">
                                    <strong>{{ row.full_name }}</strong>
                                    <span class="balance-pill">
                                        <i class="pi pi-star" aria-hidden="true" />
                                        الرصيد الحالي: {{ pointsBalanceLabel(row) }} نقطة
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="plan-badge">{{ row.plan_name }}</span>
                            </td>
                            <td class="current-point">{{ currentPointLabel(row) }}</td>
                            <td>
                                <div v-if="row.tasks.length" class="tasks-stack">
                                    <div class="tasks-strip">
                                        <article v-for="task in row.previewTasks" :key="task.id" class="task-chip">
                                            <strong>{{ task.name }}</strong>
                                            <span v-if="taskDetails(task)">{{ taskDetails(task) }}</span>
                                        </article>
                                    </div>
                                    <button
                                        v-if="row.hiddenTasks.length"
                                        type="button"
                                        class="tasks-toggle print-hidden"
                                        :aria-expanded="isTasksExpanded(row)"
                                        @click="toggleTasks(row)"
                                    >
                                        <i :class="isTasksExpanded(row) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" aria-hidden="true" />
                                        <span>{{ taskToggleLabel(row) }}</span>
                                    </button>
                                    <div v-if="row.hiddenTasks.length && isTasksExpanded(row)" class="tasks-strip tasks-strip--extra">
                                        <article v-for="task in row.hiddenTasks" :key="task.id" class="task-chip task-chip--extra">
                                            <strong>{{ task.name }}</strong>
                                            <span v-if="taskDetails(task)">{{ taskDetails(task) }}</span>
                                        </article>
                                    </div>
                                </div>
                                <span v-else class="empty-tasks">لم يتم تحديد واجب للمرة القادمة</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mobile-list" aria-label="قائمة واجبات المرة القادمة">
                <p v-if="rows.length === 0" class="mobile-empty">لا يوجد طلاب فعالين في هذه المجموعة</p>

                <article
                    v-for="(row, index) in rows"
                    :key="`mobile-${row.student_id}`"
                    class="student-card"
                    :class="rowToneClass(index)"
                >
                    <div class="student-card__header">
                        <span class="student-number">{{ row.number }}</span>
                        <div>
                            <h2>{{ row.full_name }}</h2>
                            <div class="student-card__badges">
                                <span class="plan-badge">{{ row.plan_name }}</span>
                                <span class="balance-pill">
                                    <i class="pi pi-star" aria-hidden="true" />
                                    {{ pointsBalanceLabel(row) }} نقطة
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="current-box">
                        <span>آخر إنجاز</span>
                        <strong>{{ currentPointLabel(row) }}</strong>
                    </div>

                    <div class="mobile-tasks">
                        <span class="mobile-section-label">واجبات المرة القادمة</span>
                        <div v-if="row.tasks.length" class="tasks-stack">
                            <div class="tasks-strip">
                                <article v-for="task in row.previewTasks" :key="task.id" class="task-chip">
                                    <strong>{{ task.name }}</strong>
                                    <span v-if="taskDetails(task)">{{ taskDetails(task) }}</span>
                                </article>
                            </div>
                            <button
                                v-if="row.hiddenTasks.length"
                                type="button"
                                class="tasks-toggle print-hidden"
                                :aria-expanded="isTasksExpanded(row)"
                                @click="toggleTasks(row)"
                            >
                                <i :class="isTasksExpanded(row) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" aria-hidden="true" />
                                <span>{{ taskToggleLabel(row) }}</span>
                            </button>
                            <div v-if="row.hiddenTasks.length && isTasksExpanded(row)" class="tasks-strip tasks-strip--extra">
                                <article v-for="task in row.hiddenTasks" :key="task.id" class="task-chip task-chip--extra">
                                    <strong>{{ task.name }}</strong>
                                    <span v-if="taskDetails(task)">{{ taskDetails(task) }}</span>
                                </article>
                            </div>
                        </div>
                        <span v-else class="empty-tasks">لم يتم تحديد واجب للمرة القادمة</span>
                    </div>
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

.homework-report-page {
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
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        "brand actions"
        "meta meta";
    gap: 18px;
    padding: 22px;
    overflow: hidden;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: linear-gradient(135deg, #ffffff 0%, #ffffff 62%, #f1f8f4 100%);
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
    grid-area: actions;
    align-self: start;
    justify-self: end;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.report-link-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    gap: 8px;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    background: #f0fdf4;
    color: #047857;
    padding: 9px 12px;
    font-size: 0.84rem;
    font-weight: 900;
    line-height: 1.3;
    text-decoration: none;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
    transition: transform 0.16s ease, border-color 0.16s ease, background 0.16s ease;
}

.report-link-button:hover {
    transform: translateY(-1px);
    border-color: #047857;
    background: #dcfce7;
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
    grid-area: brand;
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

.report-kicker {
    margin: 0 0 6px;
    color: #016e3d;
    font-size: 0.82rem;
    font-weight: 800;
}

.report-hero h1 {
    margin: 0;
    color: #111827;
    font-size: 2.05rem;
    font-weight: 900;
    line-height: 1.18;
    letter-spacing: 0;
}

.report-meta {
    grid-area: meta;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    align-self: end;
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

.meta-label {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
}

.report-meta strong {
    overflow-wrap: anywhere;
    color: #0f3d6e;
    font-size: 0.98rem;
    font-weight: 900;
    line-height: 1.45;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
}

.summary-card {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 6px 12px;
    min-height: 112px;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.summary-card i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    grid-row: 1 / span 2;
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: #eef8f2;
    color: #016e3d;
    font-size: 1.15rem;
}

.summary-card span {
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 800;
}

.summary-card strong {
    overflow-wrap: anywhere;
    color: #111827;
    font-size: 1.38rem;
    font-weight: 900;
    line-height: 1.25;
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
    font-size: 0.86rem;
    font-weight: 900;
}

.section-heading h2 {
    color: #111827;
    font-size: 1.32rem;
    font-weight: 900;
    line-height: 1.35;
}

.section-heading span {
    color: #475569;
    font-size: 0.92rem;
    font-weight: 800;
}

.desktop-table {
    margin-top: 12px;
    overflow: hidden;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 16px 50px rgba(15, 23, 42, 0.08);
}

.report-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    font-size: 0.9rem;
}

.number-col {
    width: 52px;
}

.student-col {
    width: 24%;
}

.plan-col {
    width: 15%;
}

.progress-col {
    width: 17%;
}

.tasks-col {
    width: auto;
}

.report-table th {
    background: #0f3d6e;
    color: #ffffff;
    padding: 13px 12px;
    text-align: start;
    font-weight: 900;
    white-space: nowrap;
}

.report-table td {
    vertical-align: top;
    border-bottom: 1px solid #e6edf5;
    padding: 12px;
}

.report-row.is-odd td {
    background: #ffffff;
}

.report-row.is-even td {
    background: #f8fafc;
}

.report-index {
    color: #0f3d6e;
    font-weight: 900;
}

.report-name {
    color: #111827;
}

.student-identity {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.student-identity strong {
    overflow-wrap: anywhere;
    color: #111827;
    font-weight: 900;
    line-height: 1.45;
}

.balance-pill {
    display: inline-flex;
    align-items: center;
    width: max-content;
    max-width: 100%;
    gap: 6px;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    background: #f0fdf4;
    color: #047857;
    padding: 5px 9px;
    font-size: 0.78rem;
    font-weight: 900;
    line-height: 1.35;
}

.balance-pill i {
    font-size: 0.75rem;
}

.plan-badge {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 5px 9px;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.35;
}

.current-point {
    color: #334155;
    font-weight: 800;
    line-height: 1.65;
    overflow-wrap: anywhere;
}

.tasks-stack {
    display: grid;
    gap: 9px;
}

.tasks-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 8px;
    margin: 0;
}

.tasks-strip--extra {
    border-top: 1px dashed #dbe5ef;
    padding-top: 9px;
}

.task-chip {
    display: grid;
    align-content: center;
    min-width: 116px;
    max-width: 190px;
    min-height: 56px;
    gap: 4px;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 8px 10px;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
}

.task-chip strong {
    overflow-wrap: anywhere;
    color: #111827;
    font-size: 0.85rem;
    font-weight: 900;
    line-height: 1.35;
}

.task-chip--extra {
    background: #f8fafc;
}

.tasks-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: max-content;
    max-width: 100%;
    gap: 7px;
    min-height: 34px;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 6px 10px;
    font-size: 0.8rem;
    font-weight: 900;
    line-height: 1.35;
    transition: border-color 0.16s ease, background 0.16s ease, color 0.16s ease;
}

.tasks-toggle:hover {
    border-color: #93c5fd;
    background: #dbeafe;
    color: #1e40af;
}

.tasks-toggle i {
    font-size: 0.75rem;
}

.task-chip span {
    overflow-wrap: anywhere;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 700;
    line-height: 1.35;
}

.empty-tasks,
.report-empty,
.mobile-empty {
    color: #64748b;
    font-weight: 800;
}

.report-empty {
    padding: 28px !important;
    text-align: center;
}

.mobile-list {
    display: none;
    margin-top: 12px;
}

.student-card {
    display: grid;
    gap: 12px;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.student-card.is-even {
    background: #f8fafc;
}

.student-card__header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.student-card__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.student-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    flex: 0 0 auto;
    border-radius: 8px;
    background: #0f3d6e;
    color: #ffffff;
    font-weight: 900;
}

.student-card h2 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 1.05rem;
    font-weight: 900;
    line-height: 1.35;
}

.current-box {
    display: grid;
    gap: 4px;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 10px;
}

.current-box span,
.mobile-section-label {
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 900;
}

.current-box strong {
    color: #0f3d6e;
    font-weight: 900;
    line-height: 1.5;
}

.mobile-tasks {
    display: grid;
    gap: 8px;
}

.mobile-tasks .tasks-strip {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.mobile-tasks .task-chip {
    min-width: 0;
    max-width: none;
}

@media (max-width: 900px) {
    .homework-report-page {
        padding: 14px;
    }

    .report-hero {
        grid-template-columns: 1fr;
        grid-template-areas:
            "actions"
            "brand"
            "meta";
        padding: 18px;
    }

    .report-actions {
        justify-self: end;
    }

    .report-meta,
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .desktop-table {
        display: none;
    }

    .mobile-list {
        display: grid;
        gap: 12px;
    }
}

@media (max-width: 520px) {
    .icon-button {
        width: 38px;
        height: 38px;
    }

    .brand-lockup {
        align-items: flex-start;
        gap: 12px;
    }

    .report-logo {
        width: 58px;
        height: 58px;
    }

    .mobile-tasks .tasks-strip {
        grid-template-columns: 1fr;
    }

    .report-hero h1 {
        font-size: 1.55rem;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }
}

@media print {
    :global(body) {
        background: #ffffff !important;
    }

    .homework-report-page {
        background: #ffffff;
        padding: 0;
    }

    .report-shell {
        width: 100%;
    }

    .print-hidden {
        display: none !important;
    }

    .report-hero,
    .summary-card,
    .desktop-table,
    .student-card {
        box-shadow: none;
    }

    .desktop-table {
        display: block;
    }

    .mobile-list {
        display: none;
    }
}
</style>
