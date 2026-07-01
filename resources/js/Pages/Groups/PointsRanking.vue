<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import StudentRankingCard from '../../components/groups/StudentRankingCard.vue';

const props = defineProps({
    ranking: {
        type: Object,
        required: true,
    },
});

const copied = shallowRef(false);
const logoUrl = computed(() => '/media/logos/logo.png');
const rows = computed(() => props.ranking.rows ?? []);
const summary = computed(() => props.ranking.summary ?? {});
const topRows = computed(() => rows.value.slice(0, 3));
const remainingRows = computed(() => rows.value.slice(3));
const hasRows = computed(() => rows.value.length > 0);
const englishNumber = (value) => Number(value ?? 0).toLocaleString('en-US');
const averagePointsLabel = computed(() => Number(summary.value.average_points ?? 0).toLocaleString('en-US', {
    maximumFractionDigits: 1,
}));
const summaryCards = computed(() => [
    {
        key: 'students',
        label: 'عدد الطلاب',
        value: englishNumber(summary.value.students_count ?? rows.value.length),
        icon: 'pi pi-users',
    },
    {
        key: 'total',
        label: 'مجموع النقاط',
        value: englishNumber(summary.value.total_points ?? 0),
        icon: 'pi pi-star',
    },
    {
        key: 'average',
        label: 'معدل الطالب',
        value: averagePointsLabel.value,
        icon: 'pi pi-chart-bar',
    },
    {
        key: 'leader',
        label: 'أعلى رصيد',
        value: englishNumber(summary.value.leader_points ?? 0),
        icon: 'pi pi-trophy',
    },
]);

const copyRankingLink = async () => {
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
    <Head title="ترتيب الطلاب حسب النقاط" />

    <main dir="rtl" class="ranking-page">
        <div class="ranking-shell">
            <section class="ranking-hero" aria-label="بيانات الترتيب">
                <div class="hero-brand">
                    <img class="ranking-logo" :src="logoUrl" alt="Logo">
                    <div>
                        <p class="hero-kicker">مشروع الحافظ المتميز</p>
                        <h1>ترتيب الطلاب حسب النقاط</h1>
                    </div>
                </div>

                <div class="hero-actions">
                    <button
                        type="button"
                        class="icon-button"
                        :class="{ 'icon-button--success': copied }"
                        :title="copied ? 'تم نسخ الرابط' : 'نسخ الرابط'"
                        :aria-label="copied ? 'تم نسخ الرابط' : 'نسخ الرابط'"
                        @click="copyRankingLink"
                    >
                        <i :class="copied ? 'pi pi-check' : 'pi pi-link'" aria-hidden="true" />
                    </button>
                </div>

                <div class="hero-meta">
                    <div>
                        <span>المركز</span>
                        <strong>{{ ranking.center_name }}</strong>
                    </div>
                    <div>
                        <span>المجموعة</span>
                        <strong>{{ ranking.group_name }}</strong>
                    </div>
                    <div>
                        <span>تاريخ العرض</span>
                        <strong>{{ ranking.generated_at }}</strong>
                    </div>
                </div>
            </section>

            <section class="summary-grid" aria-label="ملخص ترتيب النقاط">
                <article v-for="card in summaryCards" :key="card.key" class="summary-card">
                    <i :class="card.icon" aria-hidden="true" />
                    <span>{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <section class="ranking-section ranking-section--leaders" aria-label="أوائل الطلاب">
                <div class="section-heading section-heading--leaders">
                    <div>
                        <p>لوحة الصدارة</p>
                        <h2><span>الطلاب الأعلى رصيداً</span></h2>
                    </div>
                    <span v-if="summary.top_student" class="leader-chip">
                        <i class="pi pi-trophy" aria-hidden="true" />
                        المتصدر: {{ summary.top_student }}
                    </span>
                </div>

                <div v-if="hasRows" class="top-grid">
                    <StudentRankingCard
                        v-for="(row, index) in topRows"
                        :key="row.student_id"
                        :row="row"
                        :featured="true"
                        :breath-index="index"
                    />
                </div>
                <p v-else class="empty-state">لا يوجد طلاب فعالين في هذه المجموعة</p>
            </section>

            <section v-if="remainingRows.length" class="ranking-section" aria-label="باقي ترتيب الطلاب">
                <div class="section-heading">
                    <div>
                        <p>باقي الطلاب</p>
                        <h2>استمرار الترتيب</h2>
                    </div>
                    <span>{{ englishNumber(remainingRows.length) }} طالب</span>
                </div>

                <div class="ranking-list">
                    <StudentRankingCard
                        v-for="(row, index) in remainingRows"
                        :key="row.student_id"
                        :row="row"
                        :breath-index="index + topRows.length"
                    />
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
:global(body) {
    background: #f4f7fb !important;
}

:global(html.dark body),
:global(.dark body) {
    background: #f4f7fb !important;
}

.ranking-page {
    min-height: 100vh;
    overflow-x: hidden;
    background:
        linear-gradient(180deg, #eef6f2 0%, #f4f7fb 240px),
        #f4f7fb;
    color: #172033;
    font-family: Cairo, Tajawal, Arial, sans-serif;
    padding: 24px;
}

.ranking-shell {
    width: min(1180px, 100%);
    margin: 0 auto;
}

.ranking-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        "brand actions"
        "meta meta";
    gap: 18px;
    overflow: hidden;
    border: 1px solid #dce5ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 22px;
    box-shadow: 0 18px 54px rgba(15, 23, 42, 0.08);
}

.ranking-hero::before {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 7px;
    background: linear-gradient(180deg, #047857, #38bdf8);
}

.hero-brand {
    grid-area: brand;
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 16px;
}

.ranking-logo {
    width: 82px;
    height: 82px;
    flex: 0 0 auto;
    object-fit: contain;
}

.hero-kicker {
    margin: 0 0 6px;
    color: #047857;
    font-size: 0.82rem;
    font-weight: 900;
}

.ranking-hero h1 {
    margin: 0;
    overflow-wrap: anywhere;
    color: #111827;
    font-size: 2.05rem;
    font-weight: 900;
    line-height: 1.18;
    letter-spacing: 0;
}

.hero-actions {
    grid-area: actions;
    align-self: start;
    justify-self: end;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    border: 1px solid #d7e0ea;
    border-radius: 8px;
    background: #ffffff;
    color: #1f2937;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
    transition: transform 0.16s ease, border-color 0.16s ease, color 0.16s ease, background 0.16s ease;
}

.icon-button {
    width: 42px;
    height: 42px;
    padding: 0;
}

.icon-button:hover {
    transform: translateY(-1px);
    border-color: #047857;
    color: #047857;
}

.icon-button--success {
    border-color: #047857;
    color: #047857;
}

.hero-meta {
    grid-area: meta;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.hero-meta div {
    display: grid;
    gap: 6px;
    min-width: 0;
    border: 1px solid #dbe5ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 11px 12px;
}

.hero-meta span {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 900;
}

.hero-meta strong {
    overflow-wrap: anywhere;
    color: #0f3d6e;
    font-size: 0.98rem;
    font-weight: 900;
    line-height: 1.45;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
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
    background: #fef3c7;
    color: #b45309;
    font-size: 1.15rem;
}

.summary-card span {
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 900;
}

.summary-card strong {
    overflow-wrap: anywhere;
    color: #111827;
    font-size: 1.38rem;
    font-weight: 900;
    line-height: 1.25;
}

.ranking-section {
    margin-top: 24px;
}

.ranking-section--leaders {
    position: relative;
    padding-top: 4px;
}

.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
}

.section-heading p,
.section-heading h2 {
    margin: 0;
}

.section-heading p {
    color: #047857;
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
    overflow-wrap: anywhere;
    color: #475569;
    font-size: 0.92rem;
    font-weight: 900;
}

.section-heading--leaders {
    align-items: center;
}

.section-heading--leaders p {
    color: #b45309;
}

.section-heading--leaders h2 {
    font-size: 1.55rem;
}

.section-heading--leaders h2 span {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    border-bottom: 4px solid #f59e0b;
    color: #0f172a;
}

.leader-chip {
    display: inline-flex;
    align-items: center;
    width: max-content;
    max-width: 100%;
    gap: 8px;
    border: 1px solid #fde68a;
    border-radius: 8px;
    background: #fffbeb;
    color: #92400e;
    padding: 8px 11px;
    font-size: 0.84rem;
    font-weight: 900;
    line-height: 1.35;
}

.leader-chip i {
    color: #d97706;
    font-size: 0.9rem;
}

.top-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    align-items: stretch;
}

.top-grid :deep(.ranking-card--featured) {
    border-color: #fde68a;
    background:
        linear-gradient(180deg, #ffffff 0%, #fffbeb 100%);
    box-shadow: 0 18px 48px rgba(146, 64, 14, 0.14);
}

.top-grid :deep(.ranking-card) {
    grid-template-columns: 64px minmax(0, 1fr);
    grid-template-areas:
        "rank main"
        "points points";
}

.top-grid :deep(.rank-block) {
    grid-area: rank;
}

.top-grid :deep(.rank-block) {
    min-height: 68px;
    background: #ffffff;
}

.top-grid :deep(.rank-number) {
    font-size: 1.9rem;
}

.top-grid :deep(.points-block) {
    border-color: #fde68a;
    background: #fffbeb;
    color: #92400e;
}

.top-grid :deep(.student-main) {
    grid-area: main;
}

.top-grid :deep(.student-heading) {
    display: grid;
}

.top-grid :deep(.points-block) {
    grid-area: points;
}

.ranking-list {
    display: grid;
    gap: 18px;
}

.empty-state {
    margin: 0;
    border: 1px solid #dfe7ef;
    border-radius: 8px;
    background: #ffffff;
    color: #64748b;
    padding: 28px;
    text-align: center;
    font-weight: 900;
}

@media (max-width: 980px) {
    .summary-grid,
    .top-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .ranking-page {
        padding: 14px;
    }

    .ranking-hero {
        grid-template-columns: 1fr;
        grid-template-areas:
            "actions"
            "brand"
            "meta";
        padding: 18px;
    }

    .hero-actions {
        justify-self: end;
    }

    .icon-button {
        width: 38px;
        height: 38px;
        min-height: 38px;
    }

    .hero-brand {
        align-items: flex-start;
        gap: 12px;
    }

    .ranking-logo {
        width: 58px;
        height: 58px;
    }

    .ranking-hero h1 {
        font-size: 1.55rem;
    }

    .hero-meta,
    .top-grid {
        grid-template-columns: 1fr;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }

    .summary-card {
        grid-template-columns: 32px minmax(0, 1fr);
        gap: 3px 8px;
        min-height: 74px;
        padding: 9px;
    }

    .summary-card i {
        width: 32px;
        height: 32px;
        font-size: 0.92rem;
    }

    .summary-card span {
        font-size: 0.72rem;
    }

    .summary-card strong {
        font-size: 1.06rem;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }

    .section-heading--leaders h2 {
        font-size: 1.28rem;
    }

    .section-heading--leaders h2 span {
        min-height: 36px;
        border-bottom-width: 3px;
    }
}

@media (max-width: 380px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    :global(body) {
        background: #ffffff !important;
    }

    .ranking-page {
        background: #ffffff;
        padding: 0;
    }

    .ranking-shell {
        width: 100%;
    }

    .hero-actions {
        display: none;
    }
}
</style>
