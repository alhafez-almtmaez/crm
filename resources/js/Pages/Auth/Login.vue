<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Password from 'primevue/password';
import { useI18n } from 'vue-i18n';
import PrimeFloatField from '../../components/form/PrimeFloatField.vue';
import { useSystemSettings } from '../../composables/useSystemSettings';
import { useThemeMode } from '../../composables/useThemeMode';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
};

const page = usePage();
const { t } = useI18n();
const { settings } = useSystemSettings();
const { mode } = useThemeMode();
const appName = computed(() => settings.value.brandName || page.props.app?.name || t('common.app'));
const logoUrl = computed(() => {
    if (mode.value === 'dark') {
        return settings.value.logoDarkUrl ?? settings.value.logoLightUrl ?? settings.value.logoUrl ?? '';
    }

    return settings.value.logoLightUrl ?? settings.value.logoDarkUrl ?? settings.value.logoUrl ?? '';
});
</script>

<template>
    <Head :title="t('auth.login')" />

    <main class="flex min-h-screen items-center justify-center bg-(--background) px-4 py-10 text-(--foreground) sm:px-6">
        <div class="w-full max-w-md">
            <div class="mb-5 flex justify-center">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="t('auth.appLogoAlt', { appName })"
                    class="h-10 w-auto max-w-60 object-contain"
                />
                <p v-else class="truncate text-3xl font-extrabold tracking-tight text-(--accent)">{{ appName }}</p>
            </div>

            <div class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
            <h1 class="text-2xl font-semibold">{{ t('auth.signIn') }}</h1>
            <p class="mt-2 text-sm text-(--muted-foreground)">{{ t('auth.useAdminAccount') }}</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <PrimeFloatField
                    id="email"
                    v-model="form.email"
                    :label="t('auth.email')"
                    input-type="email"
                    autocomplete="email"
                    required
                    :invalid="Boolean(form.errors.email)"
                    :error="form.errors.email"
                />

                <PrimeFloatField
                    id="password"
                    v-model="form.password"
                    :label="t('auth.password')"
                    :component="Password"
                    input-type="password"
                    autocomplete="current-password"
                    required
                    :invalid="Boolean(form.errors.password)"
                    :error="form.errors.password"
                    :input-props="{ feedback: false, toggleMask: true }"
                />

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="form.remember" binary input-id="remember" />
                    <span>{{ t('auth.rememberMe') }}</span>
                </label>

                <Button
                    type="submit"
                    :label="t('auth.signIn')"
                    :loading="form.processing"
                    fluid
                />
            </form>
            </div>
        </div>
    </main>
</template>
