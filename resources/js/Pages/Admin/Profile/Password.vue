<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../../admin/navItems';
import AdminBreadcrumbs from '../../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../../components/admin/AdminLayout.vue';
import PrimeFloatField from '../../../components/form/PrimeFloatField.vue';

const { t } = useI18n();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/admin/password', {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="t('profile.updatePassword')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('profile.updatePassword')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article class="max-w-3xl rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
                <h2 class="text-2xl font-semibold">{{ t('profile.updatePassword') }}</h2>
                <p class="mt-2 text-base text-(--muted-foreground)">{{ t('profile.passwordDescription') }}</p>

                <form class="mt-6 grid gap-4" @submit.prevent="submit">
                    <PrimeFloatField
                        id="current-password"
                        v-model="form.current_password"
                        :label="t('profile.currentPassword')"
                        :component="Password"
                        input-type="password"
                        autocomplete="current-password"
                        required
                        :invalid="Boolean(form.errors.current_password)"
                        :error="form.errors.current_password"
                        :input-props="{ feedback: false, toggleMask: true }"
                    />

                    <PrimeFloatField
                        id="new-password"
                        v-model="form.password"
                        :label="t('profile.newPassword')"
                        :component="Password"
                        input-type="password"
                        autocomplete="new-password"
                        required
                        :invalid="Boolean(form.errors.password)"
                        :error="form.errors.password"
                        :input-props="{ feedback: false, toggleMask: true }"
                    />

                    <PrimeFloatField
                        id="new-password-confirmation"
                        v-model="form.password_confirmation"
                        :label="t('users.confirmPassword')"
                        :component="Password"
                        input-type="password"
                        autocomplete="new-password"
                        required
                        :invalid="Boolean(form.errors.password_confirmation)"
                        :error="form.errors.password_confirmation"
                        :input-props="{ feedback: false, toggleMask: true }"
                    />

                    <div class="mt-2 flex justify-end">
                        <Button type="submit" :label="t('profile.updatePassword')" :loading="form.processing" />
                    </div>
                </form>
            </article>
        </section>
    </AdminLayout>
</template>

