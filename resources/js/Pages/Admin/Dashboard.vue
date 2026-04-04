<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminLayout from '../../components/admin/AdminLayout.vue';

const { t } = useI18n();
const stats = computed(() => ([
    { label: t('dashboard.orders'), value: '0' },
    { label: t('dashboard.products'), value: '0' },
    { label: t('dashboard.customers'), value: '0' },
    { label: t('dashboard.revenue'), value: '$0.00' },
]));
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('dashboard.title')">
        <section class="space-y-6">
            <header>
                <h2 class="text-4xl font-semibold tracking-tight">{{ t('dashboard.title') }}</h2>
                <p class="mt-3 text-lg text-(--muted-foreground)">{{ t('dashboard.subtitle') }}</p>
            </header>

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)">
                <h3 class="text-3xl font-semibold">{{ t('dashboard.overview') }}</h3>
                <p class="mt-2 text-lg text-(--muted-foreground)">{{ t('dashboard.description') }}</p>
            </article>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="item in stats"
                    :key="item.label"
                    class="rounded-(--radius-base) border border-(--border) bg-(--card) p-4 text-(--card-foreground) shadow-(--shadow-sm)"
                >
                    <p class="text-xs uppercase text-(--muted-foreground)">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ item.value }}</p>
                </article>
            </section>
        </section>
    </AdminLayout>
</template>
