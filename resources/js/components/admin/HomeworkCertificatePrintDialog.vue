<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import MultiSelect from 'primevue/multiselect';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppToast } from '../../composables/useAppToast';

const props = defineProps({ homework: { type: Object, required: true } });
const emit = defineEmits(['close', 'printed']);
const { t, locale } = useI18n();
const appToast = useAppToast();
const rows = ref([]);
const studentIds = ref([]);
const typeKeys = ref([]);
const loading = ref(true);
const loadFailed = ref(false);
const printing = ref(false);
const endpoint = `/admin/homeworks/${props.homework.id}/certificates`;

const optionsFrom = (valueKey, labelKey) => {
    const options = new Map(rows.value.map((row) => [row[valueKey], row[labelKey]]));
    return [...options].map(([value, label]) => ({ value, label }))
        .sort((first, second) => first.label.localeCompare(second.label, locale.value));
};

const students = computed(() => optionsFrom('student_id', 'student_name'));
const types = computed(() => optionsFrom('type_key', 'type_label'));
const matching = computed(() => {
    const studentFilter = new Set(studentIds.value);
    const typeFilter = new Set(typeKeys.value);

    return rows.value.filter((row) => (!studentFilter.size || studentFilter.has(row.student_id))
        && (!typeFilter.size || typeFilter.has(row.type_key)));
});
const isReprint = computed(() => matching.value.length > 0 && matching.value.every((row) => row.printed));

const loadOptions = async () => {
    loading.value = true;
    loadFailed.value = false;
    try {
        const { data } = await axios.get(`${endpoint}/print-options`);
        rows.value = data.data;
    } catch {
        loadFailed.value = true;
    } finally {
        loading.value = false;
    }
};

const print = async () => {
    if (printing.value || !matching.value.length) return;

    printing.value = true;
    try {
        const { data } = await axios.post(`${endpoint}/print`, {
            student_ids: studentIds.value,
            type_keys: typeKeys.value,
        }, { responseType: 'blob', headers: { Accept: 'application/json' } });

        if (data.type !== 'application/pdf') throw new Error('Invalid PDF response');

        const url = URL.createObjectURL(data);
        const link = document.createElement('a');
        link.href = url;
        link.download = `homework-${props.homework.id}-certificates.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);

        emit('printed');
        await loadOptions();
    } catch (error) {
        if (error?.response?.data instanceof Blob) {
            try {
                error.response.data = JSON.parse(await error.response.data.text());
            } catch {
                // Non-JSON error responses use the translated fallback.
            }
        }
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('homeworks.certificatePrintFailed'),
        });
    } finally {
        printing.value = false;
    }
};

onMounted(loadOptions);
</script>

<template>
    <Dialog
        :visible="true"
        :header="t('homeworks.printDueCertificates')"
        :closable="!printing"
        :close-on-escape="!printing"
        modal
        class="w-[min(94vw,44rem)]"
        @update:visible="(visible) => { if (!visible) emit('close'); }"
    >
        <p class="mb-4 text-sm text-surface-500">
            {{ homework.group_name }} · {{ homework.date_formatted }}
        </p>

        <p v-if="loading" role="status" class="py-6 text-center text-surface-500">
            {{ t('homeworks.certificatePrintLoading') }}
        </p>
        <div v-else-if="loadFailed" class="space-y-3 py-4">
            <p role="alert">{{ t('homeworks.certificatePrintLoadFailed') }}</p>
            <Button :label="t('homeworks.certificatePrintRetry')" severity="secondary" @click="loadOptions" />
        </div>
        <p v-else-if="!rows.length" class="py-6 text-center text-surface-500">
            {{ t('homeworks.noDueCertificates') }}
        </p>
        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="min-w-0 space-y-1">
                    <label for="homework-print-types" class="text-sm font-medium">{{ t('homeworks.certificatePrintTypes') }}</label>
                    <MultiSelect
                        v-model="typeKeys"
                        input-id="homework-print-types"
                        :options="types"
                        option-label="label"
                        option-value="value"
                        :placeholder="t('homeworks.certificatePrintAllTypes')"
                        :disabled="printing"
                        filter
                        show-clear
                        fluid
                    />
                </div>
                <div class="min-w-0 space-y-1">
                    <label for="homework-print-students" class="text-sm font-medium">{{ t('homeworks.certificatePrintStudents') }}</label>
                    <MultiSelect
                        v-model="studentIds"
                        input-id="homework-print-students"
                        :options="students"
                        option-label="label"
                        option-value="value"
                        :placeholder="t('homeworks.certificatePrintAllStudents')"
                        :disabled="printing"
                        filter
                        show-clear
                        fluid
                    />
                </div>
            </div>

            <p class="mb-2 mt-5 text-sm font-medium" aria-live="polite">
                {{ t('homeworks.certificatePrintCount', { count: matching.length }) }}
            </p>
            <p v-if="!matching.length" class="py-3 text-sm text-surface-500">{{ t('homeworks.certificatePrintNoMatches') }}</p>
            <ul v-else class="max-h-56 divide-y divide-surface-200 overflow-y-auto dark:divide-surface-700">
                <li v-for="row in matching" :key="`${row.student_id}:${row.plan_point_id}`" class="flex items-center justify-between gap-3 py-2 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium">{{ row.student_name }}</p>
                        <p class="text-surface-500">{{ row.type_label }}</p>
                    </div>
                    <span v-if="row.printed" class="shrink-0 rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ t('homeworks.certificatePreviouslyPrinted') }}
                    </span>
                </li>
            </ul>
        </template>

        <p v-if="rows.length" class="mt-4 text-xs text-surface-500">
            {{ t('homeworks.certificatePrintPaperHint') }}
        </p>

        <template #footer>
            <Button :label="t('common.close')" severity="secondary" text :disabled="printing" @click="emit('close')" />
            <Button
                :label="t(isReprint ? 'homeworks.certificateReprintPdf' : 'homeworks.certificatePrintPdf')"
                icon="pi pi-print"
                :loading="printing"
                :disabled="loading || loadFailed || !matching.length"
                @click="print"
            />
        </template>
    </Dialog>
</template>
