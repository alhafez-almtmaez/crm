<script setup>
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
        type: String,
        default: '',
    },
    type: {
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
    error: {
        type: String,
        default: '',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium">{{ label }}</label>
        <input
            :id="id"
            :type="type"
            :autocomplete="autocomplete"
            :required="required"
            :value="modelValue"
            class="mt-1 w-full rounded-(--radius-base) border border-(--border) bg-(--background) px-3 py-2 text-sm outline-none ring-(--accent) transition focus:ring-2"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
