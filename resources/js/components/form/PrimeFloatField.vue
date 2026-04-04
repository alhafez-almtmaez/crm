<script setup>
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import FormFieldLabel from './FormFieldLabel.vue';

defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    component: {
        type: [Object, String],
        default: InputText,
    },
    inputType: {
        type: String,
        default: 'text',
    },
    autocomplete: {
        type: String,
        default: undefined,
    },
    required: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    inputProps: {
        type: Object,
        default: () => ({}),
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1">
        <FloatLabel variant="on">
            <component
                :is="component"
                :input-id="id"
                :model-value="modelValue"
                :type="inputType"
                :autocomplete="autocomplete"
                :required="required"
                :invalid="invalid"
                fluid
                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                v-bind="inputProps"
                @update:model-value="$emit('update:modelValue', $event)"
            />
            <FormFieldLabel :for-id="id" :text="label" :required="required" />
        </FloatLabel>

        <small v-if="error" class="text-sm text-red-600">{{ error }}</small>
    </div>
</template>
