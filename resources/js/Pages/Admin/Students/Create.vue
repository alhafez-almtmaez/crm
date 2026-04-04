<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import StudentFormCard from '../../../components/admin/StudentFormCard.vue';

defineProps({
    admins: {
        type: Array,
        default: () => [],
    },
    canAssignAdmin: {
        type: Boolean,
        default: false,
    },
    centers: {
        type: Array,
        default: () => [],
    },
    plans: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    first_name: '',
    second_name: '',
    middle_name: '',
    last_name: '',
    id_number: '',
    parent_phone_number: '',
    phone_number: '',
    email: '',
    date_of_birth: '',
    center_id: null,
    group_id: null,
    plan_type_id: null,
    admin_id: null,
    is_active: 1,
});
const { t } = useI18n();

const submit = () => {
    form.post('/admin/students');
};

const goBack = () => {
    router.get('/admin/students');
};
</script>

<template>
    <Head :title="t('students.createStudent')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('students.createStudent')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <StudentFormCard
                :form="form"
                :admins="admins"
                :can-assign-admin="canAssignAdmin"
                :centers="centers"
                :plans="plans"
                :submit-label="t('students.createStudent')"
                :title="t('students.newStudent')"
                :description="t('students.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
