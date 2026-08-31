<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: String, default: 'pi pi-chart-line' },
    height: { type: Number, default: 310 },
});
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 shadow-(--shadow-sm)">
        <h3 class="font-bold">{{ title }}</h3>
        <div class="grid place-items-center text-center" :style="{ minHeight: `${height}px` }">
            <div class="max-w-sm">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-[color-mix(in_oklab,var(--accent)_9%,transparent)] text-[var(--accent)]">
                    <i :class="icon" aria-hidden="true"></i>
                </span>
                <p class="mt-3 text-sm leading-6 text-(--muted-foreground)">{{ description }}</p>
            </div>
        </div>
    </article>
</template>
