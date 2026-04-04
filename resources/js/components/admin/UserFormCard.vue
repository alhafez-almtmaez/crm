<script setup>
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import Password from 'primevue/password';
import Select from 'primevue/select';
import { useI18n } from 'vue-i18n';
import FormFieldLabel from '../form/FormFieldLabel.vue';
import PrimeFloatField from '../form/PrimeFloatField.vue';

defineProps({
    description: {
        type: String,
        default: '',
    },
    form: {
        type: Object,
        required: true,
    },
    passwordLabel: {
        type: String,
        default: 'Password',
    },
    roles: {
        type: Array,
        default: () => [],
    },
    requirePassword: {
        type: Boolean,
        default: true,
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['cancel', 'submit']);
const { t } = useI18n();
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="emit('submit')">
            <PrimeFloatField
                id="user-name"
                v-model="form.name"
                :label="t('users.name')"
                autocomplete="name"
                required
                :invalid="Boolean(form.errors.name)"
                :error="form.errors.name"
            />

            <PrimeFloatField
                id="user-email"
                v-model="form.email"
                :label="t('auth.email')"
                input-type="email"
                autocomplete="email"
                required
                :invalid="Boolean(form.errors.email)"
                :error="form.errors.email"
            />

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        input-id="user-role"
                        v-model="form.role_id"
                        :options="roles"
                        option-label="name"
                        option-value="id"
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="user-role" :text="t('users.role')" required />
                </FloatLabel>
                <small v-if="form.errors.role_id" class="text-sm text-red-600">{{ form.errors.role_id }}</small>
            </div>

            <PrimeFloatField
                id="user-password"
                v-model="form.password"
                :label="passwordLabel"
                :component="Password"
                input-type="password"
                autocomplete="new-password"
                :required="requirePassword"
                :invalid="Boolean(form.errors.password)"
                :error="form.errors.password"
                :input-props="{ feedback: false, toggleMask: true }"
            />

            <PrimeFloatField
                id="user-password-confirmation"
                v-model="form.password_confirmation"
                :label="t('users.confirmPassword')"
                :component="Password"
                input-type="password"
                autocomplete="new-password"
                :required="requirePassword"
                :invalid="Boolean(form.errors.password_confirmation)"
                :error="form.errors.password_confirmation"
                :input-props="{ feedback: false, toggleMask: true }"
            />

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
