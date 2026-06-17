<script setup>
import { computed } from 'vue';
import { BarChart, DoughnutChart, LineChart } from 'vue-chart-3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'line',
        validator: (value) => ['line', 'bar', 'doughnut'].includes(value),
    },
    chartData: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        default: () => ({}),
    },
    height: {
        type: Number,
        default: 280,
    },
});

const chartComponent = computed(() => {
    const components = {
        line: LineChart,
        bar: BarChart,
        doughnut: DoughnutChart,
    };

    return components[props.type] ?? LineChart;
});

const chartStyle = computed(() => ({
    minHeight: `${props.height}px`,
}));
</script>

<template>
    <article class="chart-card">
        <header class="chart-card__header">
            <span class="chart-card__marker" aria-hidden="true"></span>
            <div>
                <h3 class="chart-card__title">{{ title }}</h3>
                <p v-if="subtitle" class="chart-card__subtitle">{{ subtitle }}</p>
            </div>
        </header>

        <div class="chart-card__body" :style="chartStyle">
            <component
                :is="chartComponent"
                :chart-data="chartData"
                :options="options"
                :height="height"
            />
        </div>
    </article>
</template>

<style scoped>
.chart-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-base);
    background:
        linear-gradient(145deg, color-mix(in oklab, var(--background) 70%, transparent), transparent 52%),
        var(--card);
    color: var(--card-foreground);
    padding: 1.15rem;
    box-shadow: 0 14px 34px rgb(15 23 42 / 0.05), var(--shadow-sm);
}

:global(:root.dark) .chart-card {
    box-shadow: 0 18px 42px rgb(0 0 0 / 0.24), var(--shadow-sm);
}

.chart-card__header {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1.05rem;
}

.chart-card__marker {
    width: 0.45rem;
    height: 2.2rem;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--accent), color-mix(in oklab, var(--accent) 34%, #0284c7));
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--accent) 9%, transparent);
}

.chart-card__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.35;
}

.chart-card__subtitle {
    margin-top: 0.35rem;
    color: var(--muted-foreground);
    font-size: 0.9rem;
    line-height: 1.5;
}

.chart-card__body {
    position: relative;
    width: 100%;
    border-radius: var(--radius-sm);
    background: color-mix(in oklab, var(--background) 48%, transparent);
    padding: 0.35rem;
}
</style>
