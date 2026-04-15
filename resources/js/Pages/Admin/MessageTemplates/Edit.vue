<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import MessageTemplateFormCard from '../../../components/admin/MessageTemplateFormCard.vue';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    key: props.template.key,
    name: props.template.name,
    locale: props.template.locale ?? 'ar',
    content: props.template.content ?? '',
    placeholders: props.template.placeholders ?? null,
    is_active: Boolean(props.template.is_active),
});

const { t } = useI18n();

const submit = () => {
    form.put(`/admin/message-templates/${props.template.id}`);
};

const goBack = () => {
    router.get('/admin/message-templates');
};
</script>

<template>
    <Head :title="t('messageTemplates.editTemplate')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('messageTemplates.editTemplate')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <MessageTemplateFormCard
                :form="form"
                :submit-label="t('common.saveChanges')"
                :title="t('messageTemplates.editTemplate')"
                :description="t('messageTemplates.editDescription')"
                @submit="submit"
                @cancel="goBack"
            />
        </section>
    </AdminLayout>
</template>
