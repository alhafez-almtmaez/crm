<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: {
        type: Object,
        required: true,
    },
    featured: {
        type: Boolean,
        default: false,
    },
    breathIndex: {
        type: Number,
        default: 0,
    },
});

const englishNumber = (value) => Number(value ?? 0).toLocaleString('en-US');
const currentPointLabel = computed(() => props.row.current_plan_point_name || 'لم يبدأ بعد');
const gapLabel = computed(() => {
    const gap = Number(props.row.points_gap_from_leader ?? 0);

    return gap === 0 ? 'في الصدارة' : `يفصله ${englishNumber(gap)} نقطة عن الصدارة`;
});
const rankTone = computed(() => {
    if (props.row.rank === 1) {
        return 'gold';
    }

    if (props.row.rank === 2) {
        return 'silver';
    }

    if (props.row.rank === 3) {
        return 'bronze';
    }

    return 'standard';
});
const cardClasses = computed(() => [
    `ranking-card--${rankTone.value}`,
    {
        'ranking-card--featured': props.featured,
    },
]);
const cardStyle = computed(() => ({
    '--breath-delay': `${props.breathIndex * 0.42}s`,
}));
</script>

<template>
    <article class="ranking-card" :class="cardClasses" :style="cardStyle">
        <div class="rank-block">
            <span class="rank-number">{{ englishNumber(row.rank) }}</span>
            <span class="rank-label">الترتيب</span>
        </div>

        <div class="student-main">
            <div class="student-heading">
                <h2>{{ row.full_name }}</h2>
                <span class="plan-pill">{{ row.plan_name }}</span>
            </div>

            <div class="student-meta">
                <span>
                    <i class="pi pi-map-marker" aria-hidden="true" />
                    {{ currentPointLabel }}
                </span>
                <span>
                    <i class="pi pi-chart-line" aria-hidden="true" />
                    {{ gapLabel }}
                </span>
            </div>
        </div>

        <div class="points-block">
            <strong>{{ englishNumber(row.points_balance) }}</strong>
            <span>نقطة</span>
        </div>
    </article>
</template>

<style scoped>
.ranking-card {
    position: relative;
    display: grid;
    grid-template-columns: 68px minmax(0, 1fr) 112px;
    align-items: center;
    gap: 16px;
    overflow: hidden;
    border: 1px solid #dce5ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 16px;
    box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
}

.ranking-card::before {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    top: 14px;
    bottom: 14px;
    width: 5px;
    border-radius: 0 8px 8px 0;
    background: #5b8def;
}

.ranking-card--featured {
    min-height: 190px;
    align-content: center;
}

.ranking-card--featured.ranking-card--gold {
    border-color: #f59e0b;
}

.ranking-card--featured.ranking-card--silver {
    border-color: #94a3b8;
    background: #f8fafc;
}

.ranking-card--featured.ranking-card--bronze {
    border-color: #d97706;
    background: #fff7ed;
}

.ranking-card--gold::before {
    background: #f59e0b;
}

.ranking-card--silver::before {
    background: #64748b;
}

.ranking-card--bronze::before {
    background: #b45309;
}

.rank-block,
.points-block {
    display: grid;
    place-items: center;
}

.rank-block {
    gap: 4px;
    min-height: 64px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
}

.ranking-card--gold .rank-block {
    border-color: #fde68a;
    background: #fffbeb;
    color: #b45309;
}

.ranking-card--silver .rank-block {
    border-color: #cbd5e1;
    background: #f8fafc;
    color: #475569;
}

.ranking-card--bronze .rank-block {
    border-color: #fed7aa;
    background: #fff7ed;
    color: #b45309;
}

.rank-number {
    font-size: 1.65rem;
    font-weight: 900;
    line-height: 1;
}

.rank-label {
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 900;
}

.student-main {
    display: grid;
    min-width: 0;
    gap: 12px;
}

.student-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
}

.student-heading h2 {
    margin: 0;
    overflow-wrap: anywhere;
    color: #111827;
    font-size: 1.12rem;
    font-weight: 900;
    line-height: 1.45;
}

.plan-pill {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    flex: 0 0 auto;
    border: 1px solid #c4b5fd;
    border-radius: 8px;
    background: #f5f3ff;
    color: #6d28d9;
    padding: 5px 9px;
    font-size: 0.78rem;
    font-weight: 900;
    line-height: 1.35;
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.student-meta span {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    gap: 6px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #475569;
    padding: 6px 9px;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.45;
}

.student-meta i {
    color: #0f766e;
    font-size: 0.78rem;
}

.points-block {
    justify-self: stretch;
    min-height: 78px;
    gap: 3px;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    background: #f0fdf4;
    color: #047857;
}

.points-block strong {
    font-size: 1.8rem;
    font-weight: 900;
    line-height: 1;
}

.points-block span {
    font-size: 0.8rem;
    font-weight: 900;
}

@media (prefers-reduced-motion: no-preference) {
    .ranking-card {
        animation: ranking-breathe 7s ease-in-out infinite;
        animation-delay: var(--breath-delay);
    }
}

@keyframes ranking-breathe {
    0%,
    100% {
        transform: translateY(0);
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
    }

    50% {
        transform: translateY(-3px);
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.1);
    }
}

@media (max-width: 760px) {
    .ranking-card {
        grid-template-columns: 48px minmax(0, 1fr);
        grid-template-areas:
            "rank main"
            "points points";
        gap: 12px;
        padding: 14px;
    }

    .rank-block {
        grid-area: rank;
        min-height: 48px;
    }

    .rank-number {
        font-size: 1.28rem;
    }

    .student-main {
        grid-area: main;
    }

    .student-heading {
        flex-direction: column;
        gap: 8px;
    }

    .points-block {
        grid-area: points;
        min-height: 62px;
    }
}
</style>
