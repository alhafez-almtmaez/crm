<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import HomeworkFormCard from '../../../components/admin/HomeworkFormCard.vue';

const props = defineProps({
    homework: {
        type: Object,
        required: true,
    },
    centers: {
        type: Array,
        default: () => [],
    },
    students: {
        type: Array,
        default: () => [],
    },
});

const mapStudents = (rows = []) => rows.map((row) => ({
    student_id: Number(row.student_id),
    full_name: row.full_name ?? '',
    plan_id: row.plan_id ?? null,
    plan_name: row.plan_name ?? null,
    group_name: row.group_name ?? null,
    points_balance: Number(row.points_balance ?? 0),
    points_balance_before: Number(row.points_balance_before ?? 0),
    points_adjustment: Number(row.points_adjustment ?? 0),
    points_adjustment_original: Number(row.points_adjustment_original ?? row.points_adjustment ?? 0),
    points_balance_after: Number(row.points_balance_after ?? 0),
    current_plan_point_name: row.current_plan_point_name ?? null,
    points: (row.points ?? []).map((point) => ({
        id: point.id ?? null,
        plan_point_id: Number(point.plan_point_id),
        name: point.name ?? '',
        points: Number(point.points ?? 0),
        is_done: Boolean(point.is_done ?? false),
        is_locked: Boolean(point.is_locked ?? false),
    })),
}));

const form = useForm({
    center_id: props.homework.center_id,
    date: props.homework.date,
    items: mapStudents(props.students),
});

const { t } = useI18n();

const submit = () => {
    form.put(`/admin/homeworks/${props.homework.id}`);
};

const goBack = () => {
    router.get('/admin/homeworks');
};
</script>

<template>
    <Head :title="t('homeworks.editHomework')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('homeworks.editHomework')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <HomeworkFormCard
                :form="form"
                :centers="centers"
                :submit-label="t('common.saveChanges')"
                :title="t('homeworks.editHomework')"
                :description="t('homeworks.editDescription')"
                lock-center-and-date
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
