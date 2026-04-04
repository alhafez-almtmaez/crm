<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import UserFormCard from '../../../components/admin/UserFormCard.vue';

defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    email: '',
    role_id: null,
    password: '',
    password_confirmation: '',
});
const { t } = useI18n();

const submit = () => {
    form.post('/admin/users');
};

const goBack = () => {
    router.get('/admin/users');
};
</script>

<template>
    <Head :title="t('users.createUser')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('users.createUser')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <UserFormCard
                :submit-label="t('users.createUser')"
                :password-label="t('auth.password')"
                :title="t('users.newUser')"
                :description="t('users.newUserDescription')"
                :form="form"
                :roles="roles"
                :require-password="true"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
