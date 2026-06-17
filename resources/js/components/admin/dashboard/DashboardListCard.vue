<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    items: {
        type: Array,
        default: () => [],
    },
    emptyText: {
        type: String,
        default: '',
    },
});

const toneNames = new Set(['success', 'warning', 'danger', 'info', 'neutral']);

const itemTone = (item) => (toneNames.has(item.tone) ? item.tone : 'neutral');
const iconClass = (item) => ['list-card__icon-symbol', item.icon ?? 'pi pi-circle'];

const linkBindings = (item) => (item.href ? { href: item.href } : {});
</script>

<template>
    <article class="list-card">
        <header class="list-card__header">
            <div>
                <h3 class="list-card__title">{{ title }}</h3>
                <p v-if="subtitle" class="list-card__subtitle">{{ subtitle }}</p>
            </div>
        </header>

        <div v-if="items.length" class="list-card__items">
            <component
                :is="item.href ? Link : 'div'"
                v-for="item in items"
                :key="item.key ?? item.title"
                v-bind="linkBindings(item)"
                class="list-card__item"
                :data-tone="itemTone(item)"
            >
                <span class="list-card__icon">
                    <i :class="iconClass(item)" aria-hidden="true"></i>
                </span>
                <span class="list-card__content">
                    <span v-if="item.eyebrow" class="list-card__eyebrow">{{ item.eyebrow }}</span>
                    <span class="list-card__item-title">{{ item.title }}</span>
                    <span v-if="item.meta" class="list-card__meta">{{ item.meta }}</span>
                </span>
                <span v-if="item.value" class="list-card__value">{{ item.value }}</span>
            </component>
        </div>

        <p v-else class="list-card__empty">{{ emptyText }}</p>
    </article>
</template>

<style scoped>
.list-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-base);
    background:
        linear-gradient(145deg, color-mix(in oklab, var(--background) 74%, transparent), transparent 48%),
        var(--card);
    color: var(--card-foreground);
    padding: 1.15rem;
    box-shadow: 0 14px 34px rgb(15 23 42 / 0.05), var(--shadow-sm);
}

:global(:root.dark) .list-card {
    box-shadow: 0 18px 42px rgb(0 0 0 / 0.24), var(--shadow-sm);
}

.list-card__header {
    margin-bottom: 1rem;
}

.list-card__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.35;
}

.list-card__subtitle {
    margin-top: 0.35rem;
    color: var(--muted-foreground);
    font-size: 0.9rem;
    line-height: 1.5;
}

.list-card__items {
    display: grid;
    gap: 0.7rem;
}

.list-card__item {
    --list-accent: #64748b;
    --list-accent-soft: #f1f5f9;
    --list-accent-border: #cbd5e1;
    --list-accent-strong: #475569;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.8rem;
    min-height: 4.1rem;
    border: 1px solid color-mix(in oklab, var(--border) 80%, transparent);
    border-radius: var(--radius-sm);
    padding: 0.75rem;
    text-decoration: none;
    transition: border-color 160ms ease, background-color 160ms ease, transform 160ms ease;
}

.list-card__item:hover {
    border-color: color-mix(in oklab, var(--list-accent) 34%, var(--border));
    background: color-mix(in oklab, var(--list-accent-soft) 62%, transparent);
    transform: translateY(-1px);
}

.list-card__item[data-tone='success'] {
    --list-accent: #059669;
    --list-accent-soft: #ecfdf5;
    --list-accent-border: #a7f3d0;
    --list-accent-strong: #047857;
}

.list-card__item[data-tone='warning'] {
    --list-accent: #d97706;
    --list-accent-soft: #fffbeb;
    --list-accent-border: #fde68a;
    --list-accent-strong: #b45309;
}

.list-card__item[data-tone='danger'] {
    --list-accent: #e11d48;
    --list-accent-soft: #fff1f2;
    --list-accent-border: #fecdd3;
    --list-accent-strong: #be123c;
}

.list-card__item[data-tone='info'] {
    --list-accent: #0284c7;
    --list-accent-soft: #f0f9ff;
    --list-accent-border: #bae6fd;
    --list-accent-strong: #0369a1;
}

:global(:root.dark) .list-card__item {
    --list-accent-soft: color-mix(in oklab, var(--list-accent) 14%, transparent);
    --list-accent-border: color-mix(in oklab, var(--list-accent) 38%, var(--border));
    --list-accent-strong: color-mix(in oklab, var(--list-accent) 72%, #ffffff);
}

.list-card__icon {
    display: inline-flex;
    width: 2.15rem;
    height: 2.15rem;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    background: var(--list-accent-soft);
    color: var(--list-accent-strong);
    box-shadow: inset 0 0 0 1px var(--list-accent-border);
}

.list-card__icon-symbol {
    font-size: 0.95rem;
}

.list-card__content {
    min-width: 0;
}

.list-card__eyebrow {
    display: block;
    color: var(--muted-foreground);
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1.3;
    text-transform: uppercase;
}

.list-card__item-title {
    display: block;
    overflow: hidden;
    font-size: 0.94rem;
    font-weight: 750;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.list-card__meta {
    display: block;
    overflow: hidden;
    color: var(--muted-foreground);
    font-size: 0.82rem;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.list-card__value {
    justify-self: end;
    border-radius: 999px;
    background: var(--list-accent-soft);
    color: var(--list-accent-strong);
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.45rem 0.6rem;
    white-space: nowrap;
}

.list-card__empty {
    margin: 0;
    border: 1px dashed var(--border);
    border-radius: var(--radius-sm);
    color: var(--muted-foreground);
    font-size: 0.9rem;
    padding: 1rem;
}
</style>
