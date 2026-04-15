<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PrimeFloatField from '../form/PrimeFloatField.vue';

const props = defineProps({
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

const localeOptions = computed(() => [
    { value: 'ar', label: t('messageTemplates.localeArabic') },
    { value: 'en', label: t('messageTemplates.localeEnglish') },
]);
</script>

<template>
    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8">
        <h2 v-if="title" class="text-2xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-3 text-lg text-(--muted-foreground)">{{ description }}</p>

        <form class="mt-6 grid gap-4" @submit.prevent="emit('submit')">
            <PrimeFloatField
                id="absence-template-key"
                v-model="props.form.key"
                :label="t('messageTemplates.key')"
                autocomplete="off"
                required
                :invalid="Boolean(props.form.errors.key)"
                :error="props.form.errors.key"
            />

            <PrimeFloatField
                id="absence-template-name"
                v-model="props.form.name"
                :label="t('messageTemplates.templateName')"
                autocomplete="off"
                required
                :invalid="Boolean(props.form.errors.name)"
                :error="props.form.errors.name"
            />

            <div class="flex flex-col gap-1">
                <label for="absence-template-locale" class="text-sm font-medium">{{ t('messageTemplates.locale') }}</label>
                <Select
                    input-id="absence-template-locale"
                    v-model="props.form.locale"
                    :options="localeOptions"
                    option-label="label"
                    option-value="value"
                    class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                />
                <small v-if="props.form.errors.locale" class="text-sm text-red-600">{{ props.form.errors.locale }}</small>
            </div>

            <div class="flex flex-col gap-1">
                <label for="absence-template-content" class="text-sm font-medium">
                    {{ t('messageTemplates.content') }}
                    <span class="text-red-600">*</span>
                </label>
                <Textarea
                    id="absence-template-content"
                    v-model="props.form.content"
                    rows="9"
                    class="w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                />
                <small v-if="props.form.errors.content" class="text-sm text-red-600">{{ props.form.errors.content }}</small>
                <small class="text-xs text-(--muted-foreground)">{{ t('messageTemplates.placeholdersHint') }}</small>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <Checkbox v-model="props.form.is_active" binary input-id="absence-template-active" />
                <span>{{ t('messageTemplates.isActive') }}</span>
            </label>
            <small v-if="props.form.errors.is_active" class="text-sm text-red-600">{{ props.form.errors.is_active }}</small>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="props.form.processing" />
            </div>
        </form>
    </article>
</template>
