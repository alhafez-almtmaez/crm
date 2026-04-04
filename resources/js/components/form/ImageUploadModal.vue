<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppToast } from '../../composables/useAppToast';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    currentUrl: {
        type: String,
        default: '',
    },
    uploadUrl: {
        type: String,
        default: '/admin/settings/brand-assets',
    },
});

const emit = defineEmits(['update:visible', 'uploaded']);

const selectedFile = ref(null);
const previewUrl = ref('');
const uploading = ref(false);
const appToast = useAppToast();
const { t } = useI18n();

const resetState = () => {
    selectedFile.value = null;
    previewUrl.value = '';
};

const close = () => {
    emit('update:visible', false);
};

const handleFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    selectedFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
};

const upload = async () => {
    if (!selectedFile.value || uploading.value) return;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    uploading.value = true;

    try {
        const { data } = await axios.post(props.uploadUrl, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        emit('uploaded', data.url);
        appToast.success(data.message ?? t('uploads.uploadCompleted'));
        close();
    } catch (error) {
        appToast.fromAxiosError(error, {
            summary: t('notifications.uploadFailedTitle'),
            fallback: t('notifications.uploadFailedDetail'),
        });
    } finally {
        uploading.value = false;
    }
};

watch(
    () => props.visible,
    (isVisible) => {
        if (!isVisible) {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
            }
            resetState();
        }
    },
);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :header="title"
        :style="{ width: 'min(32rem, 92vw)' }"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-(--muted-foreground)">{{ t('uploads.current') }}</p>
                    <div class="flex h-28 items-center justify-center rounded-md border border-(--border) bg-(--background)">
                        <img
                            v-if="currentUrl"
                            :src="currentUrl"
                            :alt="t('uploads.current')"
                            class="h-full w-full rounded-md object-contain"
                        />
                        <span v-else class="text-sm text-(--muted-foreground)">{{ t('uploads.noImageSet') }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium text-(--muted-foreground)">{{ t('uploads.selected') }}</p>
                    <div class="flex h-28 items-center justify-center rounded-md border border-(--border) bg-(--background)">
                        <img
                            v-if="previewUrl"
                            :src="previewUrl"
                            :alt="t('uploads.selectedPreview')"
                            class="h-full w-full rounded-md object-contain"
                        />
                        <span v-else class="text-sm text-(--muted-foreground)">{{ t('uploads.noFileSelected') }}</span>
                    </div>
                </div>
            </div>

            <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="block w-full rounded-md border border-(--border) bg-(--background) p-2 text-sm"
                @change="handleFileChange"
            />

            <p class="text-xs text-(--muted-foreground)">{{ t('uploads.allowedFormats') }}</p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button :label="t('common.cancel')" severity="secondary" text @click="close" />
                <Button :label="t('uploads.upload')" :loading="uploading" :disabled="!selectedFile" @click="upload" />
            </div>
        </template>
    </Dialog>
</template>
