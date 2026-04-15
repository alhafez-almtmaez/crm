<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import MessageTemplateFormCard from '../../../components/admin/MessageTemplateFormCard.vue';

const form = useForm({
    key: '',
    name: '',
    locale: 'ar',
    content: '',
    placeholders: null,
    is_active: true,
});

const { t } = useI18n();

const submit = () => {
    form.post('/admin/message-templates');
};

const goBack = () => {
    router.get('/admin/message-templates');
};
</script>

<template>
    <Head :title="t('messageTemplates.createTemplate')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('messageTemplates.createTemplate')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <MessageTemplateFormCard
                :form="form"
                :submit-label="t('messageTemplates.createTemplate')"
                :title="t('messageTemplates.newTemplate')"
                :description="t('messageTemplates.createDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
