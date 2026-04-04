<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import StudentFormCard from '../../../components/admin/StudentFormCard.vue';

const props = defineProps({
    admins: {
        type: Array,
        default: () => [],
    },
    canAssignAdmin: {
        type: Boolean,
        default: false,
    },
    student: {
        type: Object,
        required: true,
    },
    centers: {
        type: Array,
        default: () => [],
    },
    plans: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    first_name: props.student.first_name ?? '',
    second_name: props.student.second_name ?? '',
    middle_name: props.student.middle_name ?? '',
    last_name: props.student.last_name ?? '',
    id_number: props.student.id_number ?? '',
    parent_phone_number: props.student.parent_phone_number ?? '',
    phone_number: props.student.phone_number ?? '',
    email: props.student.email ?? '',
    date_of_birth: props.student.date_of_birth ?? '',
    center_id: props.student.center_id ?? null,
    group_id: props.student.group_id ?? null,
    plan_type_id: props.student.plan_type_id ?? null,
    admin_id: props.student.admin_id ?? null,
    is_active: props.student.is_active ?? 1,
});
const { t } = useI18n();

const submit = () => {
    form.put('/admin/students/' + props.student.id);
};

const goBack = () => {
    router.get('/admin/students');
};
</script>

<template>
    <Head :title="t('students.editStudent')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('students.editStudent')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <StudentFormCard
                :form="form"
                :admins="admins"
                :can-assign-admin="canAssignAdmin"
                :centers="centers"
                :plans="plans"
                :initial-groups="groups"
                :submit-label="t('common.saveChanges')"
                :title="t('students.editStudent')"
                :description="t('students.editDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
