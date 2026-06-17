<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: 'pi pi-chart-line',
    },
    tone: {
        type: String,
        default: 'emerald',
    },
});

const toneNames = new Set(['emerald', 'sky', 'amber', 'rose', 'violet']);
</script>

<template>
    <article class="metric-card" :data-tone="toneNames.has(tone) ? tone : 'emerald'">
        <div class="metric-card__header">
            <span class="metric-card__label">{{ label }}</span>
            <span class="metric-card__icon">
                <i :class="icon" aria-hidden="true"></i>
            </span>
        </div>
        <p class="metric-card__value">{{ value }}</p>
        <p v-if="subtitle" class="metric-card__subtitle">{{ subtitle }}</p>
    </article>
</template>

<style scoped>
.metric-card {
    --metric-accent: #059669;
    --metric-accent-soft: #ecfdf5;
    --metric-accent-border: #a7f3d0;
    --metric-accent-strong: #047857;
    min-height: 9rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-base);
    background:
        linear-gradient(145deg, color-mix(in oklab, var(--metric-accent-soft) 64%, transparent), transparent 58%),
        var(--card);
    color: var(--card-foreground);
    padding: 1.15rem;
    box-shadow: 0 14px 34px rgb(15 23 42 / 0.06), var(--shadow-sm);
    transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.metric-card:hover {
    border-color: color-mix(in oklab, var(--metric-accent) 32%, var(--border));
    box-shadow: 0 18px 42px rgb(15 23 42 / 0.09), var(--shadow-sm);
    transform: translateY(-1px);
}

.metric-card[data-tone='sky'] {
    --metric-accent: #0284c7;
    --metric-accent-soft: #f0f9ff;
    --metric-accent-border: #bae6fd;
    --metric-accent-strong: #0369a1;
}

.metric-card[data-tone='amber'] {
    --metric-accent: #d97706;
    --metric-accent-soft: #fffbeb;
    --metric-accent-border: #fde68a;
    --metric-accent-strong: #b45309;
}

.metric-card[data-tone='rose'] {
    --metric-accent: #e11d48;
    --metric-accent-soft: #fff1f2;
    --metric-accent-border: #fecdd3;
    --metric-accent-strong: #be123c;
}

.metric-card[data-tone='violet'] {
    --metric-accent: #7c3aed;
    --metric-accent-soft: #f5f3ff;
    --metric-accent-border: #ddd6fe;
    --metric-accent-strong: #6d28d9;
}

:global(:root.dark) .metric-card {
    --metric-accent-soft: color-mix(in oklab, var(--metric-accent) 18%, transparent);
    --metric-accent-border: color-mix(in oklab, var(--metric-accent) 42%, var(--border));
    --metric-accent-strong: color-mix(in oklab, var(--metric-accent) 72%, #ffffff);
    background:
        linear-gradient(145deg, color-mix(in oklab, var(--metric-accent) 13%, transparent), transparent 60%),
        var(--card);
    box-shadow: 0 18px 42px rgb(0 0 0 / 0.28), var(--shadow-sm);
}

.metric-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.metric-card__label {
    color: var(--muted-foreground);
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.3;
    text-transform: uppercase;
}

.metric-card__icon {
    display: inline-flex;
    width: 2.55rem;
    height: 2.55rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    background: var(--metric-accent-soft);
    color: var(--metric-accent-strong);
    box-shadow: inset 0 0 0 1px var(--metric-accent-border);
}

.metric-card__icon :deep(.pi) {
    font-size: 1.05rem;
}

.metric-card__value {
    margin-top: 1.1rem;
    font-size: clamp(1.7rem, 4vw, 2.4rem);
    font-weight: 800;
    line-height: 1;
    letter-spacing: 0;
}

.metric-card__subtitle {
    margin-top: 0.65rem;
    color: var(--muted-foreground);
    font-size: 0.9rem;
    line-height: 1.5;
}
</style>
