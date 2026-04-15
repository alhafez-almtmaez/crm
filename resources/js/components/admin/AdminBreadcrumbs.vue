<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { t } = useI18n();

const labelKeyBySegment = {
    admin: 'breadcrumbs.admin',
    dashboard: 'breadcrumbs.dashboard',
    whatsapp: 'breadcrumbs.whatsapp',
    users: 'breadcrumbs.users',
    roles: 'breadcrumbs.roles',
    plans: 'breadcrumbs.plans',
    centers: 'breadcrumbs.centers',
    groups: 'breadcrumbs.groups',
    students: 'breadcrumbs.students',
    'absence-rules': 'breadcrumbs.absenceRules',
    'message-templates': 'breadcrumbs.messageTemplates',
    'activity-logs': 'breadcrumbs.activityLogs',
    settings: 'breadcrumbs.settings',
    password: 'breadcrumbs.password',
    create: 'breadcrumbs.create',
    edit: 'breadcrumbs.edit',
};

const toTitle = (value) => value
    .replace(/[-_]/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());

const derivedItems = computed(() => {
    if (props.items.length > 0) {
        return props.items.map((item) => ({
            ...item,
            label: item.labelKey ? t(item.labelKey) : item.label,
        }));
    }

    const cleanPath = page.url.split('?')[0];
    const segments = cleanPath.split('/').filter(Boolean);
    const items = [];
    let href = '';
    let hasDashboard = false;

    for (let index = 0; index < segments.length; index += 1) {
        const segment = segments[index];
        const nextSegment = segments[index + 1] ?? '';
        const isIdSegment = /^\d+$/.test(segment);

        if (segment === 'admin') {
            href += '/admin';
            continue;
        }

        if (segment === 'dashboard') {
            hasDashboard = true;
        }

        if (isIdSegment && nextSegment === 'edit') {
            continue;
        }

        href += '/' + segment;
        const labelKey = labelKeyBySegment[segment];
        items.push({
            label: labelKey ? t(labelKey) : toTitle(segment),
            href,
        });
    }

    if (!hasDashboard && cleanPath.startsWith('/admin')) {
        return [
            { label: t('breadcrumbs.dashboard'), href: '/admin/dashboard' },
            ...items,
        ];
    }

    return items;
});
</script>

<template>
    <nav :aria-label="t('breadcrumbs.ariaLabel')">
        <ol class="flex flex-wrap items-center gap-2 text-sm">
            <li
                v-for="(item, index) in derivedItems"
                :key="item.href + item.label"
                class="flex items-center gap-2"
            >
                <span v-if="index > 0" class="text-(--muted-foreground)">/</span>
                <Link
                    v-if="index < derivedItems.length - 1"
                    :href="item.href"
                    class="text-(--muted-foreground) hover:text-(--foreground)"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="font-medium text-(--foreground)">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
