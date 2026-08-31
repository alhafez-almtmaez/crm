<script setup>
import axios from 'axios';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ColorPicker from 'primevue/colorpicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import CertificateContentTemplatesPanel from '../../components/admin/CertificateContentTemplatesPanel.vue';
import { useAppToast } from '../../composables/useAppToast';

const GENDER_VALUES = ['male', 'female'];
const ACHIEVEMENT_TYPE_VALUES = ['surah', 'part', 'three_parts'];
const COLOR_FIELDS = [
    { key: 'heading_color', labelKey: 'certificateDesign.colors.heading' },
    { key: 'student_name_color', labelKey: 'certificateDesign.colors.studentName' },
    { key: 'content_color', labelKey: 'certificateDesign.colors.content' },
    { key: 'accent_color', labelKey: 'certificateDesign.colors.accent' },
];
const props = defineProps({
    designs: {
        type: Object,
        default: () => ({}),
    },
    catalog: {
        type: Object,
        default: () => ({}),
    },
    previewAchievements: {
        type: Object,
        default: () => ({}),
    },
    previewCenters: {
        type: Object,
        default: () => ({}),
    },
    centers: {
        type: Array,
        default: () => [],
    },
    canUpdate: {
        type: Boolean,
        default: false,
    },
    contentTemplates: {
        type: Array,
        default: () => [],
    },
    contentTemplateAssignments: {
        type: Array,
        default: () => [],
    },
    effectiveContentTemplates: {
        type: [Object, Array],
        default: () => ({}),
    },
    templateVariables: {
        type: Array,
        default: () => [],
    },
    canManageContentTemplates: {
        type: Boolean,
        default: null,
    },
    canManageGlobalContentAssignments: {
        type: Boolean,
        default: null,
    },
});

const { t, te } = useI18n();
const appToast = useAppToast();
const activeWorkspace = ref('design');
const contentPreview = ref({
    templateId: null,
    templateName: '',
    sections: null,
});
const canManageContentTemplateDefinitions = computed(() => props.canManageContentTemplates === null
    ? false
    : props.canManageContentTemplates);
const canManageGlobalContentAssignments = computed(() => props.canManageGlobalContentAssignments === null
    ? false
    : props.canManageGlobalContentAssignments);
const workspaceOptions = computed(() => [
    { value: 'design', label: t('certificateDesign.workspace.design'), icon: 'pi-palette' },
    { value: 'content', label: t('certificateDesign.workspace.content'), icon: 'pi-file-edit' },
]);

const deepClone = (value) => JSON.parse(JSON.stringify(value ?? {}));
const normalizeHex = (value) => {
    const candidate = String(value ?? '').trim();
    const cleaned = candidate.replace(/^#/, '');

    return /^[0-9a-fA-F]{6}$/.test(cleaned) ? `#${cleaned.toUpperCase()}` : null;
};
const localizedCatalogLabel = (namespace, option) => {
    const key = `certificateDesign.${namespace}.${option.value}`;

    return te(key) ? t(key) : (option.label || option.value);
};

const rawThemes = computed(() => props.catalog?.themes ?? []);
const rawFonts = computed(() => props.catalog?.fonts ?? []);
const themes = computed(() => rawThemes.value.map((theme) => ({
    ...theme,
    label: localizedCatalogLabel('themes', theme),
})));
const fonts = computed(() => rawFonts.value.map((font) => ({
    ...font,
    label: localizedCatalogLabel('fonts', font),
})));
const genders = computed(() => {
    const options = props.catalog?.genders?.length
        ? props.catalog.genders
        : GENDER_VALUES.map((value) => ({ value, label: value }));

    return options.map((option) => ({
        ...option,
        label: localizedCatalogLabel('genders', option),
    }));
});
const achievementTypes = computed(() => {
    const options = props.catalog?.achievementTypes?.length
        ? props.catalog.achievementTypes
        : ACHIEVEMENT_TYPE_VALUES.map((value) => ({ value, label: value }));

    return options.map((option) => ({
        ...option,
        label: localizedCatalogLabel('types', option),
    }));
});

const findTheme = (value) => rawThemes.value.find((theme) => theme.value === value) ?? rawThemes.value[0] ?? {};
const findFont = (value) => rawFonts.value.find((font) => font.value === value) ?? rawFonts.value[0] ?? {};
const normalizeDesign = (design = {}) => {
    const theme = findTheme(design.theme ?? design.theme_key);
    const font = findFont(design.font ?? design.font_family ?? design.font_key);

    return {
        theme: design.theme ?? design.theme_key ?? theme.value ?? '',
        font: design.font ?? design.font_family ?? design.font_key ?? font.value ?? '',
        heading_color: normalizeHex(design.heading_color) ?? normalizeHex(theme.heading_color) ?? '#A8781D',
        student_name_color: normalizeHex(design.student_name_color) ?? normalizeHex(theme.student_name_color) ?? '#173F6B',
        content_color: normalizeHex(design.content_color) ?? normalizeHex(theme.content_color) ?? '#27364A',
        accent_color: normalizeHex(design.accent_color) ?? normalizeHex(theme.accent_color) ?? '#2B5F91',
    };
};
const normalizeCenterDesigns = (source = {}) => Object.fromEntries(
    ACHIEVEMENT_TYPE_VALUES.map((type) => [
        type,
        normalizeDesign(source?.[type]),
    ]),
);
const normalizeCenter = (option) => ({
    id: Number(option?.id),
    name: String(option?.name ?? '').trim(),
    center_name: String(option?.center_name ?? '').trim(),
    student_gender: String(option?.student_gender ?? ''),
    show_center_manager_signature: option?.show_center_manager_signature === true,
});
const previewCenterOptions = computed(() => {
    const source = Array.isArray(props.centers) ? props.centers : [];

    return source
        .map(normalizeCenter)
        .filter((option) => Number.isInteger(option.id)
            && option.id > 0
            && GENDER_VALUES.includes(option.student_gender)
            && option.center_name !== '');
});
const normalizedDesignsByCenter = Object.fromEntries(
    previewCenterOptions.value.map((center) => [
        String(center.id),
        normalizeCenterDesigns(props.designs?.[String(center.id)] ?? props.designs?.[center.id]),
    ]),
);
const initialAchievementType = ACHIEVEMENT_TYPE_VALUES.find(
    (type) => Array.isArray(props.previewAchievements?.[type])
        && props.previewAchievements[type].length > 0,
) ?? ACHIEVEMENT_TYPE_VALUES[0];
const initialCenterId = previewCenterOptions.value[0]?.id ?? null;
const initialCenterDesigns = initialCenterId === null
    ? normalizeCenterDesigns()
    : normalizedDesignsByCenter[String(initialCenterId)];
const form = useForm({
    center_id: initialCenterId,
    designs: deepClone(initialCenterDesigns),
});
const savedDesignsByCenter = ref(deepClone(normalizedDesignsByCenter));
const draftDesignsByCenter = ref(deepClone(normalizedDesignsByCenter));
const selectedAchievementType = ref(initialAchievementType);
const previewFrame = ref(null);
const downloadingPreviewPdf = ref(false);
const selectedPreviewPointIds = ref(Object.fromEntries(
    ACHIEVEMENT_TYPE_VALUES.map((type) => [type, null]),
));
const warmedFrames = new Map();
const previewAchievementsByType = computed(() => Object.fromEntries(
    ACHIEVEMENT_TYPE_VALUES.map((type) => {
        const options = Array.isArray(props.previewAchievements?.[type])
            ? props.previewAchievements[type]
            : [];

        return [type, options
            .map((option) => ({
                id: Number(option?.id),
                achievement_type: String(option?.achievement_type ?? ''),
                achievement_name: String(option?.achievement_name ?? '').trim(),
                plan_name: String(option?.plan_name ?? '').trim(),
                plan_point_name: String(option?.plan_point_name ?? '').trim(),
            }))
            .filter((option) => Number.isInteger(option.id)
                && option.id > 0
                && option.achievement_type === type
                && option.achievement_name !== '')];
    }),
));
const ensureCenterDesigns = (centerId) => {
    const key = String(centerId);

    if (!savedDesignsByCenter.value[key]) {
        savedDesignsByCenter.value[key] = normalizeCenterDesigns(
            props.designs?.[key] ?? props.designs?.[centerId],
        );
    }
    if (!draftDesignsByCenter.value[key]) {
        draftDesignsByCenter.value[key] = deepClone(savedDesignsByCenter.value[key]);
    }
};
const selectedPreviewCenterId = computed({
    get: () => form.center_id,
    set: (value) => {
        const normalizedId = value === null ? null : Number(value);
        const center = previewCenterOptions.value.find((option) => option.id === normalizedId) ?? null;

        if (center?.id === form.center_id) return;

        if (form.center_id !== null) {
            draftDesignsByCenter.value[String(form.center_id)] = deepClone(form.designs);
        }

        form.clearErrors();

        if (center === null) {
            form.center_id = null;
            form.designs = normalizeCenterDesigns();
            form.defaults({ center_id: null, designs: deepClone(form.designs) });

            return;
        }

        ensureCenterDesigns(center.id);
        form.center_id = center.id;
        form.designs = deepClone(draftDesignsByCenter.value[String(center.id)]);
        form.defaults({
            center_id: center.id,
            designs: deepClone(savedDesignsByCenter.value[String(center.id)]),
        });
    },
});
const currentPreviewCenter = computed(() => previewCenterOptions.value.find(
    (option) => option.id === selectedPreviewCenterId.value,
) ?? null);
const currentPreviewAchievementOptions = computed(
    () => previewAchievementsByType.value[selectedAchievementType.value] ?? [],
);
const selectedPreviewPointId = computed({
    get: () => selectedPreviewPointIds.value[selectedAchievementType.value] ?? null,
    set: (value) => {
        selectedPreviewPointIds.value[selectedAchievementType.value] = value === null
            ? null
            : Number(value);
    },
});
const currentPreviewAchievement = computed(() => currentPreviewAchievementOptions.value.find(
    (option) => option.id === selectedPreviewPointId.value,
) ?? null);
const currentDesign = computed(() => form.designs[selectedAchievementType.value]);
const selectedTheme = computed(() => themes.value.find((theme) => theme.value === currentDesign.value.theme) ?? themes.value[0] ?? {});
const selectedFont = computed(() => fonts.value.find((font) => font.value === currentDesign.value.font) ?? fonts.value[0] ?? {});
const selectedGenderLabel = computed(() => genders.value.find(
    (option) => option.value === currentPreviewCenter.value?.student_gender,
)?.label ?? '');
const selectedCenterLabel = computed(() => currentPreviewCenter.value?.center_name ?? '');
const selectedTypeLabel = computed(() => achievementTypes.value.find((option) => option.value === selectedAchievementType.value)?.label ?? '');

watch(previewCenterOptions, (options) => {
    const selectedCenter = options.find((option) => option.id === selectedPreviewCenterId.value);

    if (selectedCenter) {
        ensureCenterDesigns(selectedCenter.id);

        return;
    }

    selectedPreviewCenterId.value = options[0]?.id ?? null;
}, { immediate: true });

watch(previewAchievementsByType, (optionsByType) => {
    ACHIEVEMENT_TYPE_VALUES.forEach((type) => {
        const options = optionsByType[type] ?? [];
        const selectedId = selectedPreviewPointIds.value[type];

        if (!options.some((option) => option.id === selectedId)) {
            selectedPreviewPointIds.value[type] = options[0]?.id ?? null;
        }
    });
}, { immediate: true });

const resolvedColor = (field) => normalizeHex(currentDesign.value[field])
    ?? normalizeHex(selectedTheme.value[field])
    ?? '#222222';
const pickerValue = (field) => resolvedColor(field).replace(/^#/, '');
const hasInvalidColor = (design, field) => normalizeHex(design?.[field]) === null;
const cellHasInvalidColors = (type) => COLOR_FIELDS.some(
    (field) => hasInvalidColor(form.designs[type], field.key),
);
const hasInvalidColors = computed(() => ACHIEVEMENT_TYPE_VALUES.some(
    (type) => cellHasInvalidColors(type),
));
const currentDesignHasInvalidColors = computed(() => cellHasInvalidColors(selectedAchievementType.value));

const selectContext = (type) => {
    selectedAchievementType.value = type;
};
const previewAchievementContext = (option) => [option?.plan_name, option?.plan_point_name]
    .map((value) => String(value ?? '').trim())
    .filter(Boolean)
    .join(' · ');
const previewCenterContext = (option) => option?.name !== option?.center_name
    ? option?.name
    : '';
const previewCenterGenderLabel = (option) => genders.value.find(
    (gender) => gender.value === option?.student_gender,
)?.label ?? option?.student_gender ?? '';
const previewCenterGenderIcon = (option) => option?.student_gender === 'female'
    ? 'pi-venus'
    : 'pi-mars';
const selectTheme = (theme) => {
    if (!props.canUpdate || currentPreviewCenter.value === null || form.processing) return;

    currentDesign.value.theme = theme.value;
    COLOR_FIELDS.forEach(({ key }) => {
        currentDesign.value[key] = normalizeHex(theme[key]) ?? currentDesign.value[key];
    });
};
const updateFont = (value) => {
    if (!props.canUpdate || currentPreviewCenter.value === null || form.processing) return;

    currentDesign.value.font = value;
};
const updateColor = (field, value) => {
    if (!props.canUpdate || currentPreviewCenter.value === null || form.processing) return;

    const candidate = String(value ?? '');
    currentDesign.value[field] = candidate.startsWith('#') ? candidate : `#${candidate}`;
};
const normalizeColorField = (field) => {
    const normalized = normalizeHex(currentDesign.value[field]);
    if (normalized) currentDesign.value[field] = normalized;
};
const restoreThemeColors = () => {
    if (!props.canUpdate || currentPreviewCenter.value === null || form.processing) return;

    COLOR_FIELDS.forEach(({ key }) => {
        currentDesign.value[key] = normalizeHex(selectedTheme.value[key]) ?? currentDesign.value[key];
    });
};
const isCellDirty = (type) => {
    if (form.center_id === null) return false;

    return JSON.stringify(form.designs[type])
        !== JSON.stringify(savedDesignsByCenter.value[String(form.center_id)]?.[type]);
};
const isCenterDirty = (centerId) => {
    const key = String(centerId);
    const draft = form.center_id === Number(centerId)
        ? form.designs
        : draftDesignsByCenter.value[key];

    return JSON.stringify(draft) !== JSON.stringify(savedDesignsByCenter.value[key]);
};
const hasAnyUnsavedChanges = computed(() => previewCenterOptions.value.some(
    (center) => isCenterDirty(center.id),
));
const cellTheme = (type) => themes.value.find(
    (theme) => theme.value === form.designs[type].theme,
) ?? themes.value[0] ?? {};
const fieldError = (field) => form.errors[`designs.${selectedAchievementType.value}.${field}`] ?? '';

const previewDesignPayload = computed(() => ({
    theme: currentDesign.value.theme,
    font: currentDesign.value.font,
    heading_color: resolvedColor('heading_color'),
    student_name_color: resolvedColor('student_name_color'),
    content_color: resolvedColor('content_color'),
    accent_color: resolvedColor('accent_color'),
}));
const previewMessage = computed(() => ({
    type: 'certificate-design-preview:update',
    ...previewDesignPayload.value,
    center_id: currentPreviewCenter.value?.id ?? null,
    gender: currentPreviewCenter.value?.student_gender ?? GENDER_VALUES[0],
    achievement_type: selectedAchievementType.value,
    plan_point_id: currentPreviewAchievement.value?.id ?? null,
    content_template_id: contentPreview.value.templateId,
    content_sections: contentPreview.value.sections,
}));

const updateContentPreview = (value) => {
    contentPreview.value = {
        templateId: Number.isInteger(Number(value?.templateId)) ? Number(value.templateId) : null,
        templateName: String(value?.templateName ?? '').trim(),
        sections: value?.sections && typeof value.sections === 'object'
            ? deepClone(value.sections)
            : null,
    };
};

const sendPreviewUpdate = () => {
    if (typeof window === 'undefined' || !previewFrame.value?.contentWindow) return;

    previewFrame.value.contentWindow.postMessage(previewMessage.value, window.location.origin);
};
const handlePreviewMessage = (event) => {
    if (typeof window === 'undefined'
        || event.origin !== window.location.origin
        || event.source !== previewFrame.value?.contentWindow
        || event.data?.type !== 'certificate-design-preview:ready') {
        return;
    }

    sendPreviewUpdate();
};
const prewarmFrame = (url) => {
    const source = String(url ?? '').trim();
    if (source === '' || warmedFrames.has(source) || typeof window === 'undefined') return;

    const image = new window.Image();
    image.decoding = 'async';
    image.addEventListener('error', () => warmedFrames.delete(source), { once: true });
    image.src = source;
    warmedFrames.set(source, image);
};

watch(previewMessage, sendPreviewUpdate, { deep: true, flush: 'sync' });
watch(
    () => selectedTheme.value.frame_url,
    (url) => prewarmFrame(url),
    { immediate: true },
);

onMounted(() => window.addEventListener('message', handlePreviewMessage));
onBeforeUnmount(() => window.removeEventListener('message', handlePreviewMessage));

const downloadPreviewPdf = async () => {
    if (downloadingPreviewPdf.value
        || currentDesignHasInvalidColors.value
        || currentPreviewCenter.value === null
        || currentPreviewAchievement.value === null) {
        return;
    }

    downloadingPreviewPdf.value = true;

    try {
        const contentPayload = contentPreview.value.sections
            ? { content_template_sections: deepClone(contentPreview.value.sections) }
            : (contentPreview.value.templateId
                ? { content_template_id: contentPreview.value.templateId }
                : {});
        const response = await axios.post('/admin/certificate-designs/preview/pdf', {
            center_id: currentPreviewCenter.value.id,
            plan_point_id: currentPreviewAchievement.value.id,
            design: previewDesignPayload.value,
            ...contentPayload,
        }, {
            responseType: 'blob',
        });
        const blob = response.data instanceof Blob
            ? response.data
            : new Blob([response.data], { type: 'application/pdf' });
        const objectUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = objectUrl;
        link.download = `certificate-preview-center-${currentPreviewCenter.value.id}-${selectedAchievementType.value}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 1000);
    } catch (error) {
        if (error?.response?.data instanceof Blob) {
            try {
                const parsed = JSON.parse(await error.response.data.text());
                if (parsed && typeof parsed === 'object') error.response.data = parsed;
            } catch {
                // Keep the original Axios error when the response is not JSON.
            }
        }

        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificateDesign.previewPdfFailed'),
        });
    } finally {
        downloadingPreviewPdf.value = false;
    }
};

const submit = () => {
    if (!props.canUpdate || currentPreviewCenter.value === null || hasInvalidColors.value) return;

    const submittedCenterId = Number(form.center_id);
    const submittedDesigns = deepClone(form.designs);

    form.put('/admin/certificate-designs', {
        preserveScroll: true,
        onSuccess: () => {
            const key = String(submittedCenterId);

            savedDesignsByCenter.value[key] = deepClone(submittedDesigns);
            draftDesignsByCenter.value[key] = deepClone(submittedDesigns);

            if (form.center_id === submittedCenterId) {
                form.designs = deepClone(submittedDesigns);
                form.defaults({
                    center_id: submittedCenterId,
                    designs: deepClone(submittedDesigns),
                });
            }
        },
    });
};
</script>

<template>
    <Head :title="t('certificateDesign.title')">
        <link rel="stylesheet" href="/css/certificate-font-previews.css" />
    </Head>

    <AdminLayout :nav-items="adminNavItems" :page-title="t('certificateDesign.title')">
        <div class="space-y-6">
            <AdminBreadcrumbs />

            <header class="flex flex-col gap-5 rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm) sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] text-[var(--accent)]">
                        <i class="pi text-xl" :class="activeWorkspace === 'content' ? 'pi-file-edit' : 'pi-palette'"></i>
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold sm:text-3xl">{{ t('certificateDesign.title') }}</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-(--muted-foreground) sm:text-base">
                            {{ t(activeWorkspace === 'content'
                                ? 'certificateDesign.content.pageDescription'
                                : 'certificateDesign.description') }}
                        </p>
                    </div>
                </div>

                <Button
                    v-if="activeWorkspace === 'design'"
                    type="button"
                    icon="pi pi-save"
                    :label="form.recentlySuccessful ? t('certificateDesign.saved') : t('certificateDesign.save')"
                    :loading="form.processing"
                    :disabled="!canUpdate || !currentPreviewCenter || hasInvalidColors || form.processing"
                    class="h-11 shrink-0 px-5"
                    @click="submit"
                />
            </header>

            <nav
                class="rounded-(--radius-base) border border-(--border) bg-(--card) p-1.5 shadow-(--shadow-sm)"
                role="tablist"
                :aria-label="t('certificateDesign.workspace.label')"
            >
                <div class="grid gap-1 sm:grid-cols-2">
                    <button
                        v-for="workspace in workspaceOptions"
                        :id="`certificate-workspace-tab-${workspace.value}`"
                        :key="workspace.value"
                        type="button"
                        role="tab"
                        class="flex min-h-12 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                        :class="activeWorkspace === workspace.value
                            ? 'bg-[var(--accent)] text-[var(--accent-foreground)] shadow-sm'
                            : 'text-(--muted-foreground) hover:bg-(--muted) hover:text-(--foreground)'"
                        :aria-selected="activeWorkspace === workspace.value"
                        :aria-controls="`certificate-workspace-panel-${workspace.value}`"
                        @click="activeWorkspace = workspace.value"
                    >
                        <i class="pi" :class="workspace.icon" aria-hidden="true"></i>
                        <span>{{ workspace.label }}</span>
                    </button>
                </div>
            </nav>

            <div
                v-if="!canUpdate"
                class="flex items-start gap-3 rounded-(--radius-base) border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/35 dark:text-amber-100"
            >
                <i class="pi pi-lock mt-0.5"></i>
                <p>{{ t(activeWorkspace === 'content'
                    ? 'certificateDesign.content.readOnly'
                    : 'certificateDesign.readOnly') }}</p>
            </div>

            <div
                v-if="activeWorkspace === 'design' && form.hasErrors"
                class="flex items-start gap-3 rounded-(--radius-base) border border-red-300/60 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/35 dark:text-red-200"
            >
                <i class="pi pi-exclamation-circle mt-0.5"></i>
                <p>{{ t('certificateDesign.validationError') }}</p>
            </div>

            <article
                v-if="activeWorkspace === 'content'"
                class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)"
                aria-labelledby="certificate-content-context-title"
            >
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] text-[var(--accent)]">
                        <i class="pi pi-sliders-h" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 id="certificate-content-context-title" class="text-lg font-semibold">{{ t('certificateDesign.content.contextTitle') }}</h2>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificateDesign.content.contextHint') }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(14rem,1fr)_minmax(16rem,1fr)_minmax(14rem,1fr)]">
                    <div>
                        <label for="certificate-content-context-center" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.previewCenter') }}</label>
                        <Select
                            input-id="certificate-content-context-center"
                            v-model="selectedPreviewCenterId"
                            :options="previewCenterOptions"
                            option-label="center_name"
                            option-value="id"
                            :filter-fields="['center_name', 'name']"
                            :placeholder="t('certificateDesign.selectPreviewCenter')"
                            :filter-placeholder="t('certificateDesign.searchPreviewCenter')"
                            :empty-filter-message="t('certificateDesign.noMatchingPreviewCenter')"
                            :disabled="!previewCenterOptions.length"
                            filter
                            fluid
                        >
                            <template #value>
                                <div v-if="currentPreviewCenter" class="flex min-w-0 items-center justify-between gap-2 text-start">
                                    <span class="truncate font-semibold">{{ currentPreviewCenter.center_name }}</span>
                                    <span class="center-meta-badge center-meta-badge--gender">
                                        <i class="pi center-meta-badge__icon" :class="previewCenterGenderIcon(currentPreviewCenter)" aria-hidden="true"></i>
                                        <span>{{ previewCenterGenderLabel(currentPreviewCenter) }}</span>
                                    </span>
                                </div>
                            </template>
                        </Select>
                    </div>

                    <div>
                        <p id="certificate-content-context-type" class="mb-2 text-sm font-medium">{{ t('certificateDesign.certificateType') }}</p>
                        <SelectButton
                            v-model="selectedAchievementType"
                            :options="achievementTypes"
                            option-label="label"
                            option-value="value"
                            :allow-empty="false"
                            :disabled="!currentPreviewCenter"
                            aria-labelledby="certificate-content-context-type"
                            fluid
                        />
                    </div>

                    <div>
                        <label for="certificate-content-context-achievement" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.previewAchievement') }}</label>
                        <Select
                            input-id="certificate-content-context-achievement"
                            v-model="selectedPreviewPointId"
                            :options="currentPreviewAchievementOptions"
                            option-label="achievement_name"
                            option-value="id"
                            :filter-fields="['achievement_name', 'plan_name', 'plan_point_name']"
                            :placeholder="t('certificateDesign.previewAchievement')"
                            :filter-placeholder="t('certificateDesign.searchPreviewAchievement')"
                            :empty-filter-message="t('certificateDesign.noMatchingPreviewAchievement')"
                            :disabled="!currentPreviewAchievementOptions.length"
                            filter
                            fluid
                        >
                            <template #value>
                                <div v-if="currentPreviewAchievement" class="min-w-0 text-start">
                                    <span class="block truncate font-semibold">{{ currentPreviewAchievement.achievement_name }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-(--muted-foreground)">{{ previewAchievementContext(currentPreviewAchievement) }}</span>
                                </div>
                            </template>
                        </Select>
                    </div>
                </div>
            </article>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(24rem,0.88fr)_minmax(36rem,1.35fr)]">
                <section
                    v-show="activeWorkspace === 'design'"
                    id="certificate-workspace-panel-design"
                    class="space-y-5"
                    role="tabpanel"
                    aria-labelledby="certificate-workspace-tab-design"
                >
                    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                        <div>
                            <h2 class="text-xl font-semibold">{{ t('certificateDesign.contextTitle') }}</h2>
                            <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificateDesign.contextHint') }}</p>
                        </div>

                        <div class="mt-5 grid gap-5">
                            <div>
                                <label for="certificate-preview-center" class="mb-2 block text-sm font-medium">
                                    {{ t('certificateDesign.previewCenter') }}
                                </label>
                                <p class="mb-2 text-xs leading-5 text-(--muted-foreground)">
                                    {{ t('certificateDesign.previewCenterHint') }}
                                </p>

                                <Select
                                    v-if="previewCenterOptions.length"
                                    input-id="certificate-preview-center"
                                    v-model="selectedPreviewCenterId"
                                    :options="previewCenterOptions"
                                    option-label="center_name"
                                    option-value="id"
                                    :filter-fields="['center_name', 'name']"
                                    :placeholder="t('certificateDesign.selectPreviewCenter')"
                                    :filter-placeholder="t('certificateDesign.searchPreviewCenter')"
                                    :empty-filter-message="t('certificateDesign.noMatchingPreviewCenter')"
                                    :disabled="form.processing"
                                    filter
                                    fluid
                                >
                                    <template #value>
                                        <div v-if="currentPreviewCenter" class="flex min-w-0 items-center justify-between gap-3 text-start">
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold">{{ currentPreviewCenter.center_name }}</span>
                                                <span v-if="previewCenterContext(currentPreviewCenter)" class="mt-0.5 block truncate text-xs text-(--muted-foreground)">
                                                    {{ previewCenterContext(currentPreviewCenter) }}
                                                </span>
                                            </span>
                                            <span class="flex shrink-0 flex-wrap justify-end gap-1.5">
                                                <i
                                                    v-if="isCenterDirty(currentPreviewCenter.id)"
                                                    class="pi pi-circle-fill self-center text-[0.45rem] text-amber-500"
                                                    :title="t('certificateDesign.unsaved')"
                                                ></i>
                                                <span class="center-meta-badge center-meta-badge--gender">
                                                    <i
                                                        class="pi center-meta-badge__icon"
                                                        :class="previewCenterGenderIcon(currentPreviewCenter)"
                                                        aria-hidden="true"
                                                    ></i>
                                                    <span>{{ previewCenterGenderLabel(currentPreviewCenter) }}</span>
                                                </span>
                                                <span
                                                    class="center-meta-badge"
                                                    :class="currentPreviewCenter.show_center_manager_signature
                                                        ? 'center-meta-badge--identity-visible'
                                                        : 'center-meta-badge--identity-hidden'"
                                                >
                                                    <i
                                                        class="pi center-meta-badge__icon"
                                                        :class="currentPreviewCenter.show_center_manager_signature ? 'pi-eye' : 'pi-eye-slash'"
                                                        aria-hidden="true"
                                                    ></i>
                                                    <span>
                                                        {{ t(currentPreviewCenter.show_center_manager_signature
                                                            ? 'certificateDesign.centerIdentityVisible'
                                                            : 'certificateDesign.centerIdentityHidden') }}
                                                    </span>
                                                </span>
                                            </span>
                                        </div>
                                    </template>
                                    <template #option="{ option }">
                                        <div class="flex min-w-0 flex-1 items-center justify-between gap-3 py-0.5 text-start">
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold">{{ option.center_name }}</span>
                                                <span v-if="previewCenterContext(option)" class="mt-0.5 block truncate text-xs text-(--muted-foreground)">
                                                    {{ previewCenterContext(option) }}
                                                </span>
                                            </span>
                                            <span class="flex shrink-0 flex-wrap justify-end gap-1.5">
                                                <i
                                                    v-if="isCenterDirty(option.id)"
                                                    class="pi pi-circle-fill self-center text-[0.45rem] text-amber-500"
                                                    :title="t('certificateDesign.unsaved')"
                                                ></i>
                                                <span class="center-meta-badge center-meta-badge--gender">
                                                    <i
                                                        class="pi center-meta-badge__icon"
                                                        :class="previewCenterGenderIcon(option)"
                                                        aria-hidden="true"
                                                    ></i>
                                                    <span>{{ previewCenterGenderLabel(option) }}</span>
                                                </span>
                                                <span
                                                    class="center-meta-badge"
                                                    :class="option.show_center_manager_signature
                                                        ? 'center-meta-badge--identity-visible'
                                                        : 'center-meta-badge--identity-hidden'"
                                                >
                                                    <i
                                                        class="pi center-meta-badge__icon"
                                                        :class="option.show_center_manager_signature ? 'pi-eye' : 'pi-eye-slash'"
                                                        aria-hidden="true"
                                                    ></i>
                                                    <span>
                                                        {{ t(option.show_center_manager_signature
                                                            ? 'certificateDesign.centerIdentityVisible'
                                                            : 'certificateDesign.centerIdentityHidden') }}
                                                    </span>
                                                </span>
                                            </span>
                                        </div>
                                    </template>
                                </Select>

                                <small v-if="form.errors.center_id" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                                    {{ form.errors.center_id }}
                                </small>

                                <div
                                    v-if="currentPreviewCenter"
                                    class="mt-2 flex items-start gap-2 rounded-lg bg-[color-mix(in_oklab,var(--accent)_8%,transparent)] px-3 py-2 text-xs leading-5 text-(--muted-foreground)"
                                >
                                    <i class="pi pi-check-circle mt-0.5 shrink-0 text-[var(--accent)]"></i>
                                    <p>{{ t('certificateDesign.centerSettingsApplied') }}</p>
                                </div>

                                <div
                                    v-if="!previewCenterOptions.length"
                                    class="flex items-start gap-3 rounded-lg border border-dashed border-(--border) bg-(--background) p-3 text-sm text-(--muted-foreground)"
                                >
                                    <i class="pi pi-building mt-0.5 shrink-0"></i>
                                    <p>{{ t('certificateDesign.noPreviewCenters') }}</p>
                                </div>
                            </div>

                            <div>
                                <p id="certificate-design-type-label" class="mb-2 text-sm font-medium">{{ t('certificateDesign.certificateType') }}</p>
                                <SelectButton
                                    v-model="selectedAchievementType"
                                    :options="achievementTypes"
                                    option-label="label"
                                    option-value="value"
                                    :allow-empty="false"
                                    :disabled="!currentPreviewCenter || form.processing"
                                    aria-labelledby="certificate-design-type-label"
                                    fluid
                                />
                            </div>

                            <div>
                                <label for="certificate-preview-achievement" class="mb-2 block text-sm font-medium">
                                    {{ t('certificateDesign.previewAchievement') }}
                                </label>
                                <p class="mb-2 text-xs leading-5 text-(--muted-foreground)">
                                    {{ t('certificateDesign.previewAchievementHint') }}
                                </p>

                                <Select
                                    v-if="currentPreviewAchievementOptions.length"
                                    input-id="certificate-preview-achievement"
                                    v-model="selectedPreviewPointId"
                                    :options="currentPreviewAchievementOptions"
                                    option-label="achievement_name"
                                    option-value="id"
                                    :filter-fields="['achievement_name', 'plan_name', 'plan_point_name']"
                                    :filter-placeholder="t('certificateDesign.searchPreviewAchievement')"
                                    :empty-filter-message="t('certificateDesign.noMatchingPreviewAchievement')"
                                    filter
                                    fluid
                                >
                                    <template #value>
                                        <div v-if="currentPreviewAchievement" class="min-w-0 text-start">
                                            <span class="block truncate font-semibold">{{ currentPreviewAchievement.achievement_name }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-(--muted-foreground)">
                                                {{ previewAchievementContext(currentPreviewAchievement) }}
                                            </span>
                                        </div>
                                    </template>
                                    <template #option="{ option }">
                                        <div class="min-w-0 py-0.5 text-start">
                                            <span class="block truncate font-semibold">{{ option.achievement_name }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-(--muted-foreground)">
                                                {{ previewAchievementContext(option) }}
                                            </span>
                                        </div>
                                    </template>
                                </Select>

                                <div
                                    v-else
                                    class="flex items-start gap-3 rounded-lg border border-dashed border-(--border) bg-(--background) p-3 text-sm text-(--muted-foreground)"
                                >
                                    <i class="pi pi-info-circle mt-0.5 shrink-0"></i>
                                    <p>{{ t('certificateDesign.noPreviewAchievements') }}</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-semibold">{{ t('certificateDesign.matrixTitle') }}</h2>
                                <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificateDesign.matrixHint') }}</p>
                            </div>
                            <span class="rounded-full bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] px-2.5 py-1 text-xs font-semibold text-[var(--accent)]">
                                {{ t('certificateDesign.configurationsCount', { count: 3 }) }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-2 sm:grid-cols-3">
                            <button
                                v-for="type in achievementTypes"
                                :key="type.value"
                                type="button"
                                class="group relative flex min-w-0 items-center gap-2 rounded-lg border p-2.5 text-start transition"
                                :class="[
                                    selectedAchievementType === type.value
                                        ? 'border-[var(--accent)] bg-[color-mix(in_oklab,var(--accent)_9%,transparent)] shadow-sm'
                                        : 'border-(--border) bg-(--background) hover:border-[color-mix(in_oklab,var(--accent)_45%,var(--border))]',
                                    cellHasInvalidColors(type.value) ? '!border-red-500 ring-1 ring-red-500/25' : '',
                                ]"
                                :disabled="!currentPreviewCenter || form.processing"
                                :aria-pressed="selectedAchievementType === type.value"
                                @click="selectContext(type.value)"
                            >
                                <span
                                    class="h-8 w-8 shrink-0 rounded-lg border border-black/10 shadow-inner"
                                    :style="{ backgroundColor: normalizeHex(form.designs[type.value].accent_color) ?? '#9ca3af' }"
                                ></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-semibold">{{ type.label }}</span>
                                    <span class="mt-0.5 block truncate text-[0.7rem] text-(--muted-foreground)">
                                        {{ cellTheme(type.value).label || cellTheme(type.value).value }}
                                    </span>
                                </span>
                                <i
                                    v-if="cellHasInvalidColors(type.value)"
                                    class="pi pi-exclamation-circle text-sm text-red-500"
                                    :title="t('certificateDesign.invalidColor')"
                                ></i>
                                <i
                                    v-else-if="isCellDirty(type.value)"
                                    class="pi pi-circle-fill text-[0.45rem] text-amber-500"
                                    :title="t('certificateDesign.unsaved')"
                                ></i>
                            </button>
                        </div>
                    </article>

                    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-semibold">{{ t('certificateDesign.themeTitle') }}</h2>
                                <p class="mt-1 text-sm text-(--muted-foreground)">
                                    {{ t('certificateDesign.editingContext', { center: selectedCenterLabel, type: selectedTypeLabel }) }}
                                </p>
                            </div>
                            <span class="rounded-full border border-(--border) bg-(--background) px-3 py-1 text-xs font-medium">
                                {{ themes.length }} {{ t('certificateDesign.themeCountLabel') }}
                            </span>
                        </div>

                        <div class="mt-4 max-h-[34rem] overflow-y-auto pe-1">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="theme in themes"
                                    :key="theme.value"
                                    type="button"
                                    class="overflow-hidden rounded-xl border bg-(--background) text-start transition"
                                    :class="currentDesign.theme === theme.value
                                        ? 'border-[var(--accent)] ring-2 ring-[color-mix(in_oklab,var(--accent)_24%,transparent)]'
                                        : 'border-(--border) hover:-translate-y-0.5 hover:border-[color-mix(in_oklab,var(--accent)_45%,var(--border))] hover:shadow-sm'"
                                    :disabled="!canUpdate || !currentPreviewCenter || form.processing"
                                    :aria-pressed="currentDesign.theme === theme.value"
                                    @pointerenter="prewarmFrame(theme.frame_url)"
                                    @focus="prewarmFrame(theme.frame_url)"
                                    @click="selectTheme(theme)"
                                >
                                    <span class="relative block aspect-[297/210] overflow-hidden bg-white">
                                        <img
                                            v-if="theme.frame_url"
                                            :src="theme.frame_url"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-fill"
                                            loading="lazy"
                                        />
                                        <span class="absolute inset-x-[18%] top-[35%] h-1 rounded-full" :style="{ backgroundColor: theme.heading_color }"></span>
                                        <span class="absolute inset-x-[27%] top-[49%] h-1.5 rounded-full" :style="{ backgroundColor: theme.student_name_color }"></span>
                                    </span>
                                    <span class="flex items-center justify-between gap-3 border-t border-(--border) p-3">
                                        <span class="min-w-0 truncate text-sm font-semibold">{{ theme.label }}</span>
                                        <span class="flex shrink-0 -space-x-1 rtl:space-x-reverse">
                                            <span
                                                v-for="colorKey in COLOR_FIELDS.map((field) => field.key)"
                                                :key="colorKey"
                                                class="h-4 w-4 rounded-full border-2 border-(--background)"
                                                :style="{ backgroundColor: theme[colorKey] }"
                                            ></span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
                        <h2 class="text-xl font-semibold">{{ t('certificateDesign.typographyAndColors') }}</h2>
                        <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificateDesign.typographyAndColorsHint') }}</p>

                        <div class="mt-5">
                            <label for="certificate-font" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.font') }}</label>
                            <Select
                                input-id="certificate-font"
                                :model-value="currentDesign.font"
                                :options="fonts"
                                option-label="label"
                                option-value="value"
                                fluid
                                :disabled="!canUpdate || !currentPreviewCenter || form.processing"
                                @update:model-value="updateFont"
                            >
                                <template #option="{ option }">
                                    <div class="flex w-full items-center justify-between gap-4">
                                        <span>{{ option.label }}</span>
                                        <span class="flex shrink-0 items-baseline gap-3 text-(--muted-foreground)" dir="rtl">
                                            <span class="text-xs" :style="{ fontFamily: option.body_family }">نَصُّ الشَّهَادَةِ</span>
                                            <span class="text-lg" :style="{ fontFamily: option.display_family }">اسْمُ الطَّالِبِ</span>
                                        </span>
                                    </div>
                                </template>
                            </Select>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div v-for="field in COLOR_FIELDS" :key="field.key">
                                <label :for="`certificate-${field.key}`" class="mb-2 block text-sm font-medium">{{ t(field.labelKey) }}</label>
                                <div
                                    class="flex items-center gap-2 rounded-lg border bg-(--background) p-2 transition focus-within:ring-2"
                                    :class="hasInvalidColor(currentDesign, field.key) || fieldError(field.key)
                                        ? 'border-red-500 focus-within:ring-red-500/20'
                                        : 'border-(--border) focus-within:border-[var(--accent)] focus-within:ring-[color-mix(in_oklab,var(--accent)_18%,transparent)]'"
                                >
                                    <label :for="`certificate-${field.key}-picker`" class="sr-only">
                                        {{ t(field.labelKey) }}
                                    </label>
                                    <ColorPicker
                                        :input-id="`certificate-${field.key}-picker`"
                                        :model-value="pickerValue(field.key)"
                                        format="hex"
                                        :disabled="!canUpdate || !currentPreviewCenter || form.processing"
                                        @update:model-value="updateColor(field.key, $event)"
                                    />
                                    <InputText
                                        :id="`certificate-${field.key}`"
                                        :model-value="currentDesign[field.key]"
                                        dir="ltr"
                                        maxlength="7"
                                        class="h-9 min-w-0 flex-1 border-0 bg-transparent font-mono uppercase shadow-none"
                                        :disabled="!canUpdate || !currentPreviewCenter || form.processing"
                                        @update:model-value="updateColor(field.key, $event)"
                                        @blur="normalizeColorField(field.key)"
                                    />
                                </div>
                                <small v-if="fieldError(field.key)" class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ fieldError(field.key) }}</small>
                                <small v-else-if="hasInvalidColor(currentDesign, field.key)" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                                    {{ t('certificateDesign.invalidColor') }}
                                </small>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <Button
                                type="button"
                                icon="pi pi-refresh"
                                :label="t('certificateDesign.restoreThemeColors')"
                                severity="secondary"
                                outlined
                                size="small"
                                :disabled="!canUpdate || !currentPreviewCenter || form.processing"
                                @click="restoreThemeColors"
                            />
                        </div>
                    </article>
                </section>

                <CertificateContentTemplatesPanel
                    v-show="activeWorkspace === 'content'"
                    id="certificate-workspace-panel-content"
                    role="tabpanel"
                    aria-labelledby="certificate-workspace-tab-content"
                    :templates="contentTemplates"
                    :assignments="contentTemplateAssignments"
                    :effective-templates="effectiveContentTemplates"
                    :variables="templateVariables"
                    :centers="previewCenterOptions"
                    :achievement-types="achievementTypes"
                    :selected-center="currentPreviewCenter"
                    :selected-achievement-type="selectedAchievementType"
                    :selected-achievement="currentPreviewAchievement"
                    :can-update="canUpdate"
                    :can-manage-templates="canManageContentTemplateDefinitions"
                    :can-manage-global-assignments="canManageGlobalContentAssignments"
                    @preview-change="updateContentPreview"
                />

                <aside class="xl:sticky xl:top-6">
                    <article class="overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--card) text-(--card-foreground) shadow-(--shadow-sm)">
                        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-(--border) p-4 sm:p-5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-50"></span>
                                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                    </span>
                                    <h2 class="text-lg font-semibold">{{ t('certificateDesign.livePreview') }}</h2>
                                </div>
                                <p class="mt-1 text-xs text-(--muted-foreground)">
                                    {{ t(activeWorkspace === 'content'
                                        ? 'certificateDesign.content.previewHint'
                                        : 'certificateDesign.previewHint') }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2 text-xs font-medium">
                                <span class="rounded-full bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] px-2.5 py-1 text-[var(--accent)]">{{ selectedGenderLabel }}</span>
                                <span v-if="currentPreviewCenter" class="max-w-48 truncate rounded-full border border-(--border) bg-(--background) px-2.5 py-1" :title="currentPreviewCenter.center_name">
                                    {{ currentPreviewCenter.center_name }}
                                </span>
                                <span class="rounded-full border border-(--border) bg-(--background) px-2.5 py-1">{{ selectedTypeLabel }}</span>
                                <Button
                                    type="button"
                                    icon="pi pi-download"
                                    :label="t('certificateDesign.downloadPreviewPdf')"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    :loading="downloadingPreviewPdf"
                                    :disabled="currentDesignHasInvalidColors || !currentPreviewCenter || !currentPreviewAchievement || downloadingPreviewPdf"
                                    @click="downloadPreviewPdf"
                                />
                            </div>
                        </header>

                        <div class="bg-[linear-gradient(135deg,color-mix(in_oklab,var(--border)_65%,transparent)_25%,transparent_25%,transparent_75%,color-mix(in_oklab,var(--border)_65%,transparent)_75%)] bg-size-[20px_20px] p-3 sm:p-6">
                            <iframe
                                ref="previewFrame"
                                src="/admin/certificate-designs/preview"
                                :title="t('certificateDesign.livePreview')"
                                class="mx-auto block aspect-[297/210] w-full overflow-hidden border-0 bg-white shadow-[0_22px_60px_rgb(15_23_42/20%)]"
                                loading="eager"
                                referrerpolicy="same-origin"
                                sandbox="allow-scripts allow-same-origin"
                                @load="sendPreviewUpdate"
                            ></iframe>
                        </div>

                        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-(--border) p-4 text-xs text-(--muted-foreground)">
                            <span v-if="activeWorkspace === 'content'">
                                {{ contentPreview.templateName || t('certificateDesign.content.legacyTemplate') }}
                            </span>
                            <span v-else>{{ selectedTheme.label || t('certificateDesign.noTheme') }} · {{ selectedFont.label || t('certificateDesign.noFont') }}</span>
                            <span>{{ t('certificateDesign.a4Landscape') }}</span>
                        </footer>
                    </article>

                    <div
                        v-if="activeWorkspace === 'design'"
                        class="mt-4 flex flex-col gap-3 rounded-(--radius-base) border border-(--border) bg-(--card) p-4 shadow-(--shadow-sm) sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm text-(--muted-foreground)">
                            {{ hasAnyUnsavedChanges ? t('certificateDesign.unsavedChanges') : t('certificateDesign.allSaved') }}
                        </p>
                        <Button
                            type="button"
                            icon="pi pi-save"
                            :label="t('certificateDesign.save')"
                            :loading="form.processing"
                            :disabled="!canUpdate || !currentPreviewCenter || hasInvalidColors || form.processing"
                            class="h-10 shrink-0"
                            @click="submit"
                        />
                    </div>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.center-meta-badge {
    --center-badge-tone: var(--muted-foreground);
    --center-badge-ink: var(--foreground);

    display: inline-flex;
    height: 1.5rem;
    align-items: center;
    gap: 0.3rem;
    padding-inline: 0.5rem;
    border: 1px solid color-mix(in oklab, var(--center-badge-tone) 28%, var(--border));
    border-radius: min(var(--radius-sm), 0.5rem);
    background: color-mix(in oklab, var(--center-badge-tone) 8%, var(--background));
    color: var(--center-badge-ink);
    box-shadow: 0 1px 2px color-mix(in oklab, var(--foreground) 6%, transparent);
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.center-meta-badge__icon {
    color: var(--center-badge-tone);
    font-size: 0.64rem;
}

.center-meta-badge--gender {
    --center-badge-tone: color-mix(in oklab, var(--accent) 38%, var(--foreground));
    --center-badge-ink: color-mix(in oklab, var(--accent) 34%, var(--foreground));
}

.center-meta-badge--identity-visible {
    --center-badge-tone: var(--accent);
    --center-badge-ink: color-mix(in oklab, var(--accent) 48%, var(--foreground));
}

.center-meta-badge--identity-hidden {
    --center-badge-tone: var(--muted-foreground);
    --center-badge-ink: color-mix(in oklab, var(--muted-foreground) 82%, var(--foreground));
}
</style>
