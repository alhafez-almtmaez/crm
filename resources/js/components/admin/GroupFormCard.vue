<script setup>
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed } from 'vue';
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
    whatsappGroups: {
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

const dayOptions = computed(() => [
    { value: 'sunday', label: t('days.sunday') },
    { value: 'monday', label: t('days.monday') },
    { value: 'tuesday', label: t('days.tuesday') },
    { value: 'wednesday', label: t('days.wednesday') },
    { value: 'thursday', label: t('days.thursday') },
    { value: 'friday', label: t('days.friday') },
    { value: 'saturday', label: t('days.saturday') },
]);
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

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        input-id="group-serialized"
                        v-model="form.group_serialized"
                        :options="whatsappGroups"
                        option-label="label"
                        option-value="value"
                        filter
                        show-clear
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="group-serialized" :text="t('groups.whatsappGroup')" />
                </FloatLabel>
                <small v-if="form.errors.group_serialized" class="text-sm text-red-600">{{ form.errors.group_serialized }}</small>
                <small v-if="whatsappGroups.length === 0" class="text-xs text-(--muted-foreground)">{{ t('groups.noWhatsappGroups') }}</small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <MultiSelect
                        input-id="group-working-days"
                        v-model="form.working_days"
                        :options="dayOptions"
                        option-label="label"
                        option-value="value"
                        display="chip"
                        :max-selected-labels="4"
                        :selected-items-label="t('common.selectedCount')"
                        class="h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                    />
                    <FormFieldLabel for-id="group-working-days" :text="t('groups.workingDays')" required />
                </FloatLabel>
                <small v-if="form.errors.working_days" class="text-sm text-red-600">{{ form.errors.working_days }}</small>
                <small v-if="form.errors['working_days.0']" class="text-sm text-red-600">{{ form.errors['working_days.0'] }}</small>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="emit('cancel')" />
                <Button type="submit" :label="submitLabel" :loading="form.processing" />
            </div>
        </form>
    </article>
</template>
