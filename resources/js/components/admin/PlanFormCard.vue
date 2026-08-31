<script setup>
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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
const categoryOptions = computed(() => [
    { value: 'quran', label: t('plans.categoryQuran') },
    { value: 'sunnah', label: t('plans.categorySunnah') },
]);
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="emit('submit')">
            <PrimeFloatField
                id="plan-name"
                v-model="form.name"
                :label="t('plans.planName')"
                autocomplete="off"
                required
                :invalid="Boolean(form.errors.name)"
                :error="form.errors.name"
            />

            <div class="flex flex-col gap-2">
                <label id="plan-category-label" class="text-sm font-medium text-(--foreground)">
                    {{ t('plans.planCategory') }}
                    <span class="text-red-600">*</span>
                </label>
                <SelectButton
                    v-model="form.category"
                    :options="categoryOptions"
                    option-label="label"
                    option-value="value"
                    :allow-empty="false"
                    :invalid="Boolean(form.errors.category)"
                    aria-labelledby="plan-category-label"
                    fluid
                />
                <small class="text-xs text-(--muted-foreground)">{{ t('plans.categoryHint') }}</small>
                <small v-if="form.errors.category" class="text-sm text-red-600">{{ form.errors.category }}</small>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
