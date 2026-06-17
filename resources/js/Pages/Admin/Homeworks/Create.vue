<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import HomeworkFormCard from '../../../components/admin/HomeworkFormCard.vue';

const props = defineProps({
    centers: {
        type: Array,
        default: () => [],
    },
    selected_center_id: {
        type: Number,
        default: null,
    },
    selected_date: {
        type: String,
        default: '',
    },
    students: {
        type: Array,
        default: () => [],
    },
    existing_homework_id: {
        type: Number,
        default: null,
    },
});

const mapStudents = (rows = []) => rows.map((row) => ({
    student_id: Number(row.student_id),
    full_name: row.full_name ?? '',
    plan_id: row.plan_id ?? null,
    plan_name: row.plan_name ?? null,
    group_name: row.group_name ?? null,
    points_balance: Number(row.points_balance ?? 0),
    points_adjustment: Number(row.points_adjustment ?? 0),
    points_adjustment_original: Number(row.points_adjustment_original ?? row.points_adjustment ?? 0),
    current_plan_point_name: row.current_plan_point_name ?? null,
    points: (row.points ?? []).map((point) => ({
        plan_point_id: Number(point.plan_point_id),
        name: point.name ?? '',
        points: Number(point.points ?? 0),
        is_done: Boolean(point.is_done ?? false),
        is_locked: Boolean(point.is_locked ?? false),
    })),
}));

const form = useForm({
    center_id: props.selected_center_id,
    date: props.selected_date,
    items: mapStudents(props.students),
});

watch(
    () => props.selected_center_id,
    (value) => {
        form.center_id = value;
    },
);

watch(
    () => props.selected_date,
    (value) => {
        form.date = value;
    },
);

watch(
    () => props.students,
    (value) => {
        form.items = mapStudents(value);
    },
    { immediate: true },
);

const { t } = useI18n();

const submit = () => {
    form.post('/admin/homeworks');
};

const goBack = () => {
    router.get('/admin/homeworks');
};

const reloadStudents = () => {
    router.get('/admin/homeworks/create', {
        center_id: form.center_id,
        date: form.date,
    });
};

const openExisting = () => {
    if (!props.existing_homework_id) {
        return;
    }

    router.get(`/admin/homeworks/${props.existing_homework_id}/edit`);
};
</script>

<template>
    <Head :title="t('homeworks.createHomework')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('homeworks.createHomework')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article
                v-if="existing_homework_id"
                class="rounded-(--radius-base) border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-semibold">{{ t('homeworks.alreadyExists') }}</p>
                <div class="mt-3">
                    <button
                        type="button"
                        class="rounded-md bg-amber-700 px-4 py-2 text-sm text-white"
                        @click="openExisting"
                    >
                        {{ t('homeworks.openExisting') }}
                    </button>
                </div>
            </article>

            <HomeworkFormCard
                :form="form"
                :centers="centers"
                :submit-label="t('homeworks.createHomework')"
                :title="t('homeworks.newHomework')"
                :description="t('homeworks.createDescription')"
                @submit="submit"
                @cancel="goBack"
                @reload="reloadStudents"
            />
        </section>
    </AdminLayout>
</template>
