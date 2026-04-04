<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppToast } from '../../composables/useAppToast';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    endpoint: {
        type: String,
        default: '',
    },
    entityName: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const appToast = useAppToast();
const loading = ref(false);
const logs = ref([]);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const asText = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value);
};

const toDiffRows = (changes) => {
    const oldValues = changes?.old ?? {};
    const newValues = changes?.attributes ?? {};
    const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])];

    return keys.map((key) => ({
        field: key,
        oldValue: asText(oldValues[key]),
        newValue: asText(newValues[key]),
    }));
};

const fetchLogs = async () => {
    if (!props.endpoint) {
        logs.value = [];
        return;
    }

    loading.value = true;

    try {
        const { data } = await axios.get(props.endpoint);
        logs.value = (data?.data ?? []).map((log) => ({
            ...log,
            diffRows: toDiffRows(log.changes),
        }));
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.loadFailedTitle'),
            fallback: t('notifications.loadFailedDetail'),
        });
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            fetchLogs();
        }
    },
);
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        dismissable-mask
        :header="t('activityLogs.historyFor', { name: entityName || '-' })"
        :style="{ width: 'min(980px, 96vw)' }"
    >
        <div v-if="loading" class="py-12 text-center text-sm text-(--muted-foreground)">
            {{ t('common.loading') }}
        </div>

        <div v-else-if="logs.length === 0" class="py-12 text-center text-sm text-(--muted-foreground)">
            {{ t('activityLogs.noHistory') }}
        </div>

        <div v-else class="space-y-4">
            <article
                v-for="log in logs"
                :key="log.id"
                class="rounded-md border border-(--border) bg-(--card) p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-(--foreground)">{{ log.event || log.description }}</span>
                        <span class="rounded-full bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] px-2 py-1 text-xs font-medium text-(--foreground)">
                            {{ log.log_name || 'default' }}
                        </span>
                    </div>
                    <p class="text-xs text-(--muted-foreground)">{{ log.created_at_formatted }}</p>
                </div>

                <p class="mt-1 text-sm text-(--muted-foreground)">
                    {{ t('activityLogs.by') }}: {{ log.causer_display }}
                </p>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-(--border)">
                                <th class="px-2 py-2 text-start font-semibold">{{ t('activityLogs.field') }}</th>
                                <th class="px-2 py-2 text-start font-semibold">{{ t('activityLogs.before') }}</th>
                                <th class="px-2 py-2 text-start font-semibold">{{ t('activityLogs.after') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="log.diffRows.length === 0">
                                <td class="px-2 py-3 text-(--muted-foreground)" colspan="3">
                                    {{ t('activityLogs.noChanges') }}
                                </td>
                            </tr>
                            <tr v-for="row in log.diffRows" :key="`${log.id}-${row.field}`" class="border-b border-(--border) last:border-0">
                                <td class="px-2 py-2 font-medium">{{ row.field }}</td>
                                <td class="px-2 py-2 text-(--muted-foreground)">{{ row.oldValue }}</td>
                                <td class="px-2 py-2 text-(--foreground)">{{ row.newValue }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>

        <template #footer>
            <Button :label="t('common.close')" severity="secondary" text @click="visible = false" />
        </template>
    </Dialog>
</template>

