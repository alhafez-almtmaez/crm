<script setup>
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import MultiSelect from 'primevue/multiselect';
import { useI18n } from 'vue-i18n';
import FormFieldLabel from '../form/FormFieldLabel.vue';
import PrimeFloatField from '../form/PrimeFloatField.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Array,
        default: () => [],
    },
    submitLabel: {
        type: String,
        default: 'Save Role',
    },
});

const emit = defineEmits(['cancel', 'submit']);
const { t } = useI18n();
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <form class="grid gap-4" @submit.prevent="emit('submit')">
            <PrimeFloatField
                id="role-name"
                v-model="form.name"
                :label="t('roles.roleName')"
                autocomplete="off"
                required
                :invalid="Boolean(form.errors.name)"
                :error="form.errors.name"
            />

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <MultiSelect
                        input-id="role-permissions"
                        v-model="form.permissions"
                        :options="permissions"
                        option-label="name"
                        option-value="id"
                        filter
                        display="chip"
                        :max-selected-labels="4"
                        :selected-items-label="t('common.selectedCount')"
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="role-permissions" :text="t('roles.permissions')" />
                </FloatLabel>
                <small v-if="form.errors.permissions" class="text-sm text-red-600">{{ form.errors.permissions }}</small>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
