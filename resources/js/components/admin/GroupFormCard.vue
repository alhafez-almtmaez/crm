<script setup>
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
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
    centers: {
        type: Array,
        default: () => [],
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
                id="group-name"
                v-model="form.name"
                :label="t('groups.groupName')"
                autocomplete="off"
                required
                :invalid="Boolean(form.errors.name)"
                :error="form.errors.name"
            />

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        input-id="group-center-id"
                        v-model="form.center_id"
                        :options="centers"
                        option-label="name"
                        option-value="id"
                        filter
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="group-center-id" :text="t('groups.center')" required />
                </FloatLabel>
                <small v-if="form.errors.center_id" class="text-sm text-red-600">{{ form.errors.center_id }}</small>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
