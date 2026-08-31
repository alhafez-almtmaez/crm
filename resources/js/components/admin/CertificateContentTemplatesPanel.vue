<script setup>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import { useConfirm } from 'primevue/useconfirm';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppToast } from '../../composables/useAppToast';

const SECTION_KEYS = [
    'title',
    'quote_first',
    'quote_second',
    'intro',
    'student_line',
    'achievement_line',
    'closing',
];
const REQUIRED_SECTION_VARIABLES = {
    intro: ['center_name'],
    student_line: ['student_name'],
    achievement_line: ['achievement_label', 'achievement_name'],
};
const VARIABLE_DEFAULT_SECTIONS = {
    center_name: 'intro',
    student_name: 'student_line',
    achievement_label: 'achievement_line',
    achievement_name: 'achievement_line',
};
const SECTION_MAX_LENGTHS = {
    title: 120,
    quote_first: 180,
    quote_second: 180,
    intro: 400,
    student_line: 160,
    achievement_line: 450,
    closing: 450,
};
const ACHIEVEMENT_TYPES = [
    'surah',
    'part',
    'three_parts',
    'sunnah_book',
    'sunnah_part',
];
const SOURCE_VALUES = [
    'center_type',
    'center_all',
    'gender_type',
    'gender_all',
    'global_type',
    'global_all',
    'legacy',
];
const VARIABLE_LABEL_KEYS = {
    student_name: 'studentName',
    center_name: 'centerName',
    achievement_label: 'achievementLabel',
    achievement_name: 'achievementName',
    certificate_number: 'certificateNumber',
    plan_name: 'planName',
    plan_point_name: 'planPointName',
    hijri_date: 'hijriDate',
    gregorian_date: 'gregorianDate',
};

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
    assignments: {
        type: Array,
        default: () => [],
    },
    effectiveTemplates: {
        type: [Object, Array],
        default: () => ({}),
    },
    variables: {
        type: Array,
        default: () => [],
    },
    centers: {
        type: Array,
        default: () => [],
    },
    achievementTypes: {
        type: Array,
        default: () => [],
    },
    selectedCenter: {
        type: Object,
        default: null,
    },
    selectedAchievementType: {
        type: String,
        default: 'surah',
    },
    selectedAchievement: {
        type: Object,
        default: null,
    },
    canUpdate: {
        type: Boolean,
        default: false,
    },
    canManageTemplates: {
        type: Boolean,
        default: false,
    },
    canManageGlobalAssignments: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['preview-change']);
const { t } = useI18n();
const confirm = useConfirm();
const appToast = useAppToast();

const blankSections = () => Object.fromEntries(SECTION_KEYS.map((key) => [key, '']));
const cloneSections = (source = {}) => Object.fromEntries(
    SECTION_KEYS.map((key) => [key, typeof source?.[key] === 'string' ? source[key] : '']),
);
const normalizePreviewSections = (source = {}) => Object.fromEntries(
    SECTION_KEYS.map((key) => [
        key,
        typeof source?.[key] === 'string'
            ? source[key].replace(/\r\n?/g, '\n').trim()
            : '',
    ]),
);
const templateIdentity = (value) => {
    const numeric = Number(value);

    return Number.isInteger(numeric) && numeric > 0 ? numeric : null;
};
const sameId = (left, right) => templateIdentity(left) !== null
    && templateIdentity(left) === templateIdentity(right);
const normalizeTemplate = (template = {}) => ({
    ...template,
    id: templateIdentity(template.id),
    key: String(template.key ?? ''),
    name: String(template.name ?? '').trim(),
    is_system: template.is_system === true,
    is_active: template.is_active !== false,
    sections: cloneSections(template.sections),
    assignments_count: Math.max(0, Number(template.assignments_count ?? 0) || 0),
    update_url: String(template.update_url ?? '').trim(),
    delete_url: String(template.delete_url ?? '').trim(),
});
const normalizeAssignment = (assignment = {}) => {
    const centerId = templateIdentity(assignment.center_id);
    const studentGender = ['male', 'female'].includes(assignment.student_gender)
        ? assignment.student_gender
        : null;
    const inferredScope = centerId !== null
        ? 'center'
        : (studentGender !== null ? 'gender' : 'global');
    const scopeType = ['global', 'gender', 'center'].includes(assignment.scope_type)
        ? assignment.scope_type
        : inferredScope;
    const achievementType = [...ACHIEVEMENT_TYPES, 'all'].includes(assignment.achievement_type)
        ? assignment.achievement_type
        : 'all';

    return {
        ...assignment,
        id: templateIdentity(assignment.id),
        template_id: templateIdentity(assignment.template_id),
        scope_type: scopeType,
        center_id: centerId,
        student_gender: studentGender,
        achievement_type: achievementType,
        delete_url: String(assignment.delete_url ?? assignment.destroy_url ?? '').trim(),
    };
};
const normalizedTemplates = computed(() => (Array.isArray(props.templates) ? props.templates : [])
    .map(normalizeTemplate)
    .filter((template) => template.id !== null)
    .sort((left, right) => {
        if (left.is_system !== right.is_system) return left.is_system ? -1 : 1;

        return left.name.localeCompare(right.name, 'ar');
    }));
const normalizedAssignments = computed(() => (Array.isArray(props.assignments) ? props.assignments : [])
    .map(normalizeAssignment)
    .filter((assignment) => assignment.id !== null && assignment.template_id !== null));

const sectionCatalog = computed(() => SECTION_KEYS.map((key) => ({
    key,
    label: t(`certificateDesign.content.sections.${key}.label`),
    hint: t(`certificateDesign.content.sections.${key}.hint`),
    placement: t(`certificateDesign.content.sections.${key}.placement`),
    requiredVariables: REQUIRED_SECTION_VARIABLES[key] ?? [],
    maxLength: SECTION_MAX_LENGTHS[key],
    rows: {
        title: 2,
        quote_first: 2,
        quote_second: 2,
        intro: 3,
        student_line: 2,
        achievement_line: 4,
        closing: 4,
    }[key] ?? 3,
})));
const fallbackVariables = computed(() => [
    { key: 'student_name', label: t('certificateDesign.content.variables.studentName'), sample: 'أَحْمَد مُحَمَّد العَبْدُالله' },
    { key: 'center_name', label: t('certificateDesign.content.variables.centerName'), sample: 'مركز السلام القرآني' },
    { key: 'achievement_label', label: t('certificateDesign.content.variables.achievementLabel'), sample: 'سُورَةَ' },
    { key: 'achievement_name', label: t('certificateDesign.content.variables.achievementName'), sample: 'مَرْيَم' },
    { key: 'plan_name', label: t('certificateDesign.content.variables.planName'), sample: 'خطة الحفظ' },
    { key: 'plan_point_name', label: t('certificateDesign.content.variables.planPointName'), sample: 'إتمام سورة مريم' },
    { key: 'hijri_date', label: t('certificateDesign.content.variables.hijriDate'), sample: '١٥ رَبِيع الأَوَّل ١٤٤٨' },
    { key: 'gregorian_date', label: t('certificateDesign.content.variables.gregorianDate'), sample: '٢٠٢٦/٠٨/٢٨' },
    { key: 'certificate_number', label: t('certificateDesign.content.variables.certificateNumber'), sample: 'HMT-2026-001' },
]);
const variableCatalog = computed(() => {
    const source = Array.isArray(props.variables) && props.variables.length
        ? props.variables
        : fallbackVariables.value;

    return source
        .map((variable) => {
            if (typeof variable === 'string') {
                return {
                    key: variable,
                    label: variable,
                    description: '',
                    sample: '',
                    token: `{{ ${variable} }}`,
                };
            }

            const key = String(variable?.key ?? '').trim().replace(/^\{\{\s*|\s*}}$/g, '');

            const labelKey = VARIABLE_LABEL_KEYS[key];

            return {
                key,
                label: labelKey
                    ? t(`certificateDesign.content.variables.${labelKey}`)
                    : String(variable?.label ?? key),
                description: labelKey
                    ? t(`certificateDesign.content.variableDescriptions.${labelKey}`)
                    : String(variable?.description ?? ''),
                sample: String(variable?.sample ?? ''),
                token: `{{ ${key} }}`,
            };
        })
        .filter((variable) => /^[a-zA-Z0-9_.-]+$/.test(variable.key));
});
const knownVariableKeys = computed(() => new Set(variableCatalog.value.map((variable) => variable.key)));

const templateSearch = ref('');
const selectedTemplateId = ref(null);
const editorMode = ref('idle');
const editor = ref({
    id: null,
    name: '',
    is_active: true,
    is_system: false,
    sections: blankSections(),
});
const savedEditorState = ref('');
const preferredTemplateId = ref(null);
const activeSectionKey = ref('intro');
const sectionInputs = new Map();
const savingTemplate = ref(false);
const deletingTemplateId = ref(null);
const templateErrors = ref({});
const assignmentErrors = ref({});
const requestError = ref('');
const assigning = ref(false);
const deletingAssignmentId = ref(null);

const serializedEditor = () => JSON.stringify({
    name: String(editor.value.name ?? '').trim(),
    is_active: editor.value.is_active === true,
    sections: cloneSections(editor.value.sections),
});
savedEditorState.value = serializedEditor();
const isEditorDirty = computed(() => editorMode.value === 'create'
    || (editorMode.value === 'edit' && serializedEditor() !== savedEditorState.value));
const selectedTemplate = computed(() => normalizedTemplates.value.find(
    (template) => sameId(template.id, selectedTemplateId.value),
) ?? null);
const filteredTemplates = computed(() => {
    const search = templateSearch.value.trim().toLocaleLowerCase();
    if (search === '') return normalizedTemplates.value;

    return normalizedTemplates.value.filter((template) => [template.name, template.key]
        .some((value) => String(value).toLocaleLowerCase().includes(search)));
});
const activeTemplateOptions = computed(() => normalizedTemplates.value
    .filter((template) => template.is_active)
    .map((template) => ({
        value: template.id,
        label: template.name,
        is_system: template.is_system,
    })));

const extractEffectiveRaw = () => {
    const centerId = templateIdentity(props.selectedCenter?.id);
    const achievementType = props.selectedAchievementType;
    const source = props.effectiveTemplates;

    if (centerId === null || !ACHIEVEMENT_TYPES.includes(achievementType)) return null;

    if (Array.isArray(source)) {
        return source.find((item) => Number(item?.center_id) === centerId
            && item?.achievement_type === achievementType) ?? null;
    }

    if (!source || typeof source !== 'object') return null;

    return source[String(centerId)]?.[achievementType]
        ?? source[centerId]?.[achievementType]
        ?? null;
};
const currentEffective = computed(() => {
    const raw = extractEffectiveRaw();
    if (raw === null || raw === undefined) {
        return { template_id: null, assignment_id: null, source: 'legacy', raw: null };
    }

    if (Number.isInteger(Number(raw))) {
        return {
            template_id: templateIdentity(raw),
            assignment_id: null,
            source: 'legacy',
            raw,
        };
    }

    const source = SOURCE_VALUES.includes(raw.source)
        ? raw.source
        : (SOURCE_VALUES.includes(raw.source_type) ? raw.source_type : 'legacy');

    return {
        template_id: templateIdentity(raw.template_id ?? raw.template?.id ?? raw.id),
        assignment_id: templateIdentity(raw.assignment_id ?? raw.assignment?.id),
        source,
        source_label: String(raw.source_label ?? '').trim(),
        raw,
    };
});
const effectiveTemplate = computed(() => normalizedTemplates.value.find(
    (template) => sameId(template.id, currentEffective.value.template_id),
) ?? (currentEffective.value.raw?.template ? normalizeTemplate(currentEffective.value.raw.template) : null));
const effectiveSourceLabel = computed(() => currentEffective.value.source_label
    || t(`certificateDesign.content.sources.${currentEffective.value.source}`));
const accentBadgeClass = 'bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] text-[var(--accent)]';

const editorTokensBySection = computed(() => Object.fromEntries(SECTION_KEYS.map((sectionKey) => {
    const value = editor.value.sections?.[sectionKey] ?? '';

    return [sectionKey, Array.from(value.matchAll(/\{\{\s*([a-zA-Z0-9_.-]+)\s*}}/g), (match) => match[1])];
})));
const editorTokens = computed(() => Object.values(editorTokensBySection.value).flat());
const unknownTokens = computed(() => [...new Set(editorTokens.value.filter(
    (key) => !knownVariableKeys.value.has(key),
))]);
const unsafeSections = computed(() => SECTION_KEYS.filter((key) => {
    const value = String(editor.value.sections?.[key] ?? '');

    return /<\/?[a-z][^>]*>/iu.test(value)
        || value.includes('{!!')
        || value.includes('!!}')
        || /@(php|if|unless|foreach|for|while|switch|include|extends|section|yield|auth|guest|can|cannot|error|verbatim|once|push|stack|vite)\b/iu.test(value);
}));
const missingRequiredVariables = computed(() => Object.entries(REQUIRED_SECTION_VARIABLES).flatMap(
    ([section, variables]) => variables
        .filter((variable) => !editorTokensBySection.value[section]?.includes(variable))
        .map((variable) => ({ section, variable })),
));
const misplacedVariables = computed(() => Object.entries(VARIABLE_DEFAULT_SECTIONS).flatMap(
    ([variable, expectedSection]) => SECTION_KEYS
        .filter((section) => section !== expectedSection
            && editorTokensBySection.value[section]?.includes(variable))
        .map((section) => ({ section, variable, expectedSection })),
));
const misplacedVariableMessage = computed(() => {
    const misplaced = misplacedVariables.value[0];
    if (!misplaced) return '';

    return t('certificateDesign.content.validation.variableWrongSection', {
        variable: `{{ ${misplaced.variable} }}`,
        section: t(`certificateDesign.content.sections.${misplaced.expectedSection}.label`),
    });
});
const requiredTokenWarnings = computed(() => missingRequiredVariables.value.map(
    ({ variable }) => variable,
));

const setSectionInput = (key, instance) => {
    if (instance) sectionInputs.set(key, instance);
    else sectionInputs.delete(key);
};
const textareaElement = (key) => {
    const instance = sectionInputs.get(key);
    const element = instance?.$el ?? instance;

    if (element?.tagName === 'TEXTAREA') return element;

    return element?.querySelector?.('textarea') ?? null;
};
const insertVariable = async (variable) => {
    if (!props.canManageTemplates || savingTemplate.value || editorMode.value === 'idle') return;

    const previouslyActiveSection = activeSectionKey.value;
    const preferredSection = VARIABLE_DEFAULT_SECTIONS[variable.key];
    const sectionKey = SECTION_KEYS.includes(preferredSection)
        ? preferredSection
        : (SECTION_KEYS.includes(activeSectionKey.value) ? activeSectionKey.value : 'intro');
    activeSectionKey.value = sectionKey;
    const element = textareaElement(sectionKey);
    const content = String(editor.value.sections[sectionKey] ?? '');
    const escapedKey = String(variable.key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const existingToken = SECTION_KEYS.includes(preferredSection)
        ? new RegExp(`\\{\\{\\s*${escapedKey}\\s*}}`).exec(content)
        : null;

    if (existingToken) {
        await nextTick();
        const existingElement = textareaElement(sectionKey);
        const tokenStart = existingToken.index;
        const tokenEnd = tokenStart + existingToken[0].length;
        existingElement?.focus();
        existingElement?.setSelectionRange?.(tokenStart, tokenEnd);

        return;
    }

    const shouldKeepCaret = previouslyActiveSection === sectionKey;
    const start = shouldKeepCaret && Number.isInteger(element?.selectionStart)
        ? element.selectionStart
        : content.length;
    const end = shouldKeepCaret && Number.isInteger(element?.selectionEnd)
        ? element.selectionEnd
        : start;
    const prefix = start > 0 && !/\s$/.test(content.slice(0, start)) ? ' ' : '';
    const suffix = end < content.length && !/^\s/.test(content.slice(end)) ? ' ' : '';
    const insertion = `${prefix}${variable.token}${suffix}`;

    editor.value.sections[sectionKey] = `${content.slice(0, start)}${insertion}${content.slice(end)}`;
    await nextTick();

    const updatedElement = textareaElement(sectionKey);
    const cursor = start + insertion.length;
    updatedElement?.focus();
    updatedElement?.setSelectionRange?.(cursor, cursor);
};

const updateSectionValue = (sectionKey, value) => {
    if (!SECTION_KEYS.includes(sectionKey)) return;

    editor.value.sections[sectionKey] = String(value ?? '');
    activeSectionKey.value = sectionKey;
};

const editorFromTemplate = (template) => ({
    id: template.id,
    name: template.name,
    is_active: template.is_active,
    is_system: template.is_system,
    sections: cloneSections(template.sections),
});
const loadTemplate = (template) => {
    if (!template) {
        selectedTemplateId.value = null;
        editorMode.value = 'idle';
        editor.value = {
            id: null,
            name: '',
            is_active: true,
            is_system: false,
            sections: blankSections(),
        };
        savedEditorState.value = serializedEditor();

        return;
    }

    selectedTemplateId.value = template.id;
    editorMode.value = 'edit';
    editor.value = editorFromTemplate(template);
    savedEditorState.value = serializedEditor();
    templateErrors.value = {};
    requestError.value = '';
};
const discardConfirmation = (event, action) => {
    if (!isEditorDirty.value) {
        action();

        return;
    }

    confirm.require({
        target: event?.currentTarget ?? event?.target ?? document.body,
        message: t('certificateDesign.content.confirmDiscard'),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('certificateDesign.content.discardChanges'),
            severity: 'warn',
        },
        accept: action,
    });
};
const requestTemplateSelection = (template, event) => {
    if (sameId(template.id, selectedTemplateId.value) && editorMode.value === 'edit') return;

    discardConfirmation(event, () => loadTemplate(template));
};
const startCreate = (event) => {
    if (!props.canManageTemplates) return;

    discardConfirmation(event, () => {
        selectedTemplateId.value = null;
        editorMode.value = 'create';
        editor.value = {
            id: null,
            name: '',
            is_active: true,
            is_system: false,
            sections: blankSections(),
        };
        savedEditorState.value = serializedEditor();
        templateErrors.value = {};
        requestError.value = '';
        activeSectionKey.value = 'title';
        nextTick(() => textareaElement('title')?.focus());
    });
};
const startDuplicate = (event) => {
    if (!props.canManageTemplates || selectedTemplate.value === null) return;

    const source = selectedTemplate.value;
    discardConfirmation(event, () => {
        selectedTemplateId.value = null;
        editorMode.value = 'create';
        editor.value = {
            id: null,
            name: t('certificateDesign.content.copyName', { name: source.name }),
            is_active: true,
            is_system: false,
            sections: cloneSections(source.sections),
        };
        savedEditorState.value = serializedEditor();
        templateErrors.value = {};
        requestError.value = '';
    });
};
const cancelEditing = (event) => {
    discardConfirmation(event, () => {
        const fallback = selectedTemplate.value
            ?? effectiveTemplate.value
            ?? null;
        loadTemplate(fallback);
    });
};

const responseErrors = (error) => {
    const errors = error?.response?.data?.errors;

    return errors && typeof errors === 'object' ? errors : {};
};
const firstError = (errors, key) => {
    const value = errors?.[key];

    return Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '');
};
const validateTemplateLocally = () => {
    const errors = {};
    if (String(editor.value.name ?? '').trim() === '') {
        errors.name = t('certificateDesign.content.validation.nameRequired');
    }
    SECTION_KEYS.forEach((key) => {
        if (String(editor.value.sections?.[key] ?? '').trim() === '') {
            errors[`sections.${key}`] = t('certificateDesign.content.validation.sectionRequired');
        }
    });
    missingRequiredVariables.value.forEach(({ section, variable }) => {
        const path = `sections.${section}`;
        if (!errors[path]) {
            errors[path] = t('certificateDesign.content.validation.requiredVariable', {
                variable: `{{ ${variable} }}`,
            });
        }
    });
    misplacedVariables.value.forEach(({ section, variable, expectedSection }) => {
        const path = `sections.${section}`;
        if (!errors[path]) {
            errors[path] = t('certificateDesign.content.validation.variableWrongSection', {
                variable: `{{ ${variable} }}`,
                section: t(`certificateDesign.content.sections.${expectedSection}.label`),
            });
        }
    });
    if (unknownTokens.value.length) {
        errors.sections = t('certificateDesign.content.validation.unknownVariables', {
            variables: unknownTokens.value.join('، '),
        });
    }
    unsafeSections.value.forEach((section) => {
        errors[`sections.${section}`] = t('certificateDesign.content.validation.plainTextOnly');
    });

    templateErrors.value = errors;

    return Object.keys(errors).length === 0;
};
const reloadContentProps = (preferredId = null) => new Promise((resolve) => {
    preferredTemplateId.value = templateIdentity(preferredId);
    router.reload({
        only: ['contentTemplates', 'contentTemplateAssignments', 'effectiveContentTemplates', 'templateVariables'],
        preserveScroll: true,
        onFinish: resolve,
    });
});
const saveTemplate = async () => {
    if (!props.canManageTemplates || savingTemplate.value || editorMode.value === 'idle') return;
    if (!validateTemplateLocally()) return;

    savingTemplate.value = true;
    requestError.value = '';
    templateErrors.value = {};
    const payload = {
        name: String(editor.value.name).trim(),
        is_active: editor.value.is_active === true,
        sections: cloneSections(editor.value.sections),
    };

    try {
        const isUpdate = editorMode.value === 'edit' && templateIdentity(editor.value.id) !== null;
        const endpoint = isUpdate
            ? (selectedTemplate.value?.update_url || `/admin/certificate-content-templates/${editor.value.id}`)
            : '/admin/certificate-content-templates';
        const { data } = isUpdate
            ? await axios.put(endpoint, payload)
            : await axios.post(endpoint, payload);
        const saved = data?.template ? normalizeTemplate(data.template) : null;
        const savedId = saved?.id ?? editor.value.id;

        if (saved?.id) loadTemplate(saved);
        appToast.success(data?.message ?? t(isUpdate
            ? 'certificateDesign.content.templateUpdated'
            : 'certificateDesign.content.templateCreated'));
        await reloadContentProps(savedId);
    } catch (error) {
        templateErrors.value = responseErrors(error);
        requestError.value = String(error?.response?.data?.message ?? t('certificateDesign.content.requestFailed'));
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificateDesign.content.requestFailed'),
        });
    } finally {
        savingTemplate.value = false;
    }
};
const deleteTemplate = async (template) => {
    if (!props.canManageTemplates || template.is_system || deletingTemplateId.value !== null) return;

    deletingTemplateId.value = template.id;
    requestError.value = '';

    try {
        const { data } = await axios.delete(
            template.delete_url || `/admin/certificate-content-templates/${template.id}`,
        );
        if (sameId(selectedTemplateId.value, template.id)) loadTemplate(null);
        appToast.success(data?.message ?? t('certificateDesign.content.templateDeleted'));
        await reloadContentProps();
    } catch (error) {
        requestError.value = String(error?.response?.data?.message ?? t('certificateDesign.content.deleteFailed'));
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('certificateDesign.content.deleteFailed'),
        });
    } finally {
        deletingTemplateId.value = null;
    }
};
const askDeleteTemplate = (template, event) => {
    if (!props.canManageTemplates || template.is_system) return;

    confirm.require({
        target: event?.currentTarget ?? event?.target ?? document.body,
        message: t('certificateDesign.content.confirmDeleteTemplate', { name: template.name }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('certificateDesign.content.deleteTemplate'),
            severity: 'danger',
        },
        accept: () => deleteTemplate(template),
    });
};

const genderOptions = computed(() => [
    { value: 'male', label: t('certificateDesign.genders.male') },
    { value: 'female', label: t('certificateDesign.genders.female') },
]);
const scopeOptions = computed(() => {
    const options = [{ value: 'center', label: t('certificateDesign.content.scopes.center') }];
    if (props.canManageGlobalAssignments) {
        options.unshift(
            { value: 'global', label: t('certificateDesign.content.scopes.global') },
            { value: 'gender', label: t('certificateDesign.content.scopes.gender') },
        );
    }

    return options;
});
const centerOptions = computed(() => (Array.isArray(props.centers) ? props.centers : [])
    .map((center) => ({
        ...center,
        id: templateIdentity(center?.id),
        center_name: String(center?.center_name ?? center?.name ?? '').trim(),
    }))
    .filter((center) => center.id !== null));
const achievementTypeOptions = computed(() => [
    { value: 'all', label: t('certificateDesign.content.allAchievementTypes') },
    ...(Array.isArray(props.achievementTypes) && props.achievementTypes.length
        ? props.achievementTypes
        : ACHIEVEMENT_TYPES.map((value) => ({ value, label: t(`certificateDesign.types.${value}`) }))),
]);
const selectedTypeLabel = computed(() => achievementTypeOptions.value.find(
    (option) => option.value === props.selectedAchievementType,
)?.label ?? props.selectedAchievementType);
const assignmentForm = ref({
    template_id: null,
    scope_type: 'center',
    center_id: null,
    student_gender: 'male',
    achievement_type: 'surah',
});
const resetAssignmentForContext = () => {
    const centerId = templateIdentity(props.selectedCenter?.id);
    const centerGender = ['male', 'female'].includes(props.selectedCenter?.student_gender)
        ? props.selectedCenter.student_gender
        : 'male';
    const resolvedTemplateId = currentEffective.value.template_id
        ?? selectedTemplateId.value
        ?? activeTemplateOptions.value[0]?.value
        ?? null;

    assignmentForm.value = {
        template_id: resolvedTemplateId,
        scope_type: 'center',
        center_id: centerId,
        student_gender: centerGender,
        achievement_type: ACHIEVEMENT_TYPES.includes(props.selectedAchievementType)
            ? props.selectedAchievementType
            : 'all',
    };
    assignmentErrors.value = {};
};
const assignmentMatchesForm = (assignment) => {
    if (assignment.scope_type !== assignmentForm.value.scope_type
        || assignment.achievement_type !== assignmentForm.value.achievement_type) {
        return false;
    }
    if (assignment.scope_type === 'center') {
        return sameId(assignment.center_id, assignmentForm.value.center_id);
    }
    if (assignment.scope_type === 'gender') {
        return assignment.student_gender === assignmentForm.value.student_gender;
    }

    return true;
};
const exactAssignment = computed(() => normalizedAssignments.value.find(assignmentMatchesForm) ?? null);
const exactAssignmentTemplate = computed(() => normalizedTemplates.value.find(
    (template) => sameId(template.id, exactAssignment.value?.template_id),
) ?? null);
const assignmentTargetReady = computed(() => {
    if (assignmentForm.value.scope_type === 'center') {
        return templateIdentity(assignmentForm.value.center_id) !== null;
    }
    if (assignmentForm.value.scope_type === 'gender') {
        return ['male', 'female'].includes(assignmentForm.value.student_gender);
    }

    return assignmentForm.value.scope_type === 'global';
});
const selectedAssignmentTemplate = computed(() => normalizedTemplates.value.find(
    (template) => sameId(template.id, assignmentForm.value.template_id),
) ?? null);
const canSubmitAssignment = computed(() => props.canUpdate
    && !assigning.value
    && templateIdentity(assignmentForm.value.template_id) !== null
    && selectedAssignmentTemplate.value?.is_active === true
    && assignmentTargetReady.value
    && [...ACHIEVEMENT_TYPES, 'all'].includes(assignmentForm.value.achievement_type));
const assignmentPayload = () => ({
    template_id: templateIdentity(assignmentForm.value.template_id),
    scope_type: assignmentForm.value.scope_type,
    center_id: assignmentForm.value.scope_type === 'center'
        ? templateIdentity(assignmentForm.value.center_id)
        : null,
    student_gender: assignmentForm.value.scope_type === 'gender'
        ? assignmentForm.value.student_gender
        : null,
    achievement_type: assignmentForm.value.achievement_type,
});
const saveAssignment = async () => {
    if (!canSubmitAssignment.value) return;

    assigning.value = true;
    assignmentErrors.value = {};
    requestError.value = '';

    try {
        const { data } = await axios.put('/admin/certificate-content-template-assignments', assignmentPayload());
        appToast.success(data?.message ?? t('certificateDesign.content.assignmentSaved'));
        await reloadContentProps(assignmentForm.value.template_id);
    } catch (error) {
        assignmentErrors.value = responseErrors(error);
        requestError.value = String(error?.response?.data?.message ?? t('certificateDesign.content.assignmentFailed'));
        appToast.fromAxiosError(error, {
            summary: t('notifications.requestFailedTitle'),
            fallback: t('certificateDesign.content.assignmentFailed'),
        });
    } finally {
        assigning.value = false;
    }
};
const applyToCurrentContext = async () => {
    if (!props.canUpdate || templateIdentity(props.selectedCenter?.id) === null) return;

    assignmentForm.value.scope_type = 'center';
    assignmentForm.value.center_id = templateIdentity(props.selectedCenter.id);
    assignmentForm.value.student_gender = null;
    assignmentForm.value.achievement_type = props.selectedAchievementType;
    assignmentForm.value.template_id = selectedTemplate.value?.is_active
        ? selectedTemplate.value.id
        : assignmentForm.value.template_id;
    await saveAssignment();
};
const deleteAssignment = async (assignment) => {
    if (!props.canUpdate || assignment?.id === null || deletingAssignmentId.value !== null) return;

    deletingAssignmentId.value = assignment.id;
    requestError.value = '';

    try {
        const { data } = await axios.delete(
            assignment.delete_url || `/admin/certificate-content-template-assignments/${assignment.id}`,
        );
        appToast.success(data?.message ?? t('certificateDesign.content.assignmentRemoved'));
        await reloadContentProps(selectedTemplateId.value);
    } catch (error) {
        requestError.value = String(error?.response?.data?.message ?? t('certificateDesign.content.assignmentRemoveFailed'));
        appToast.fromAxiosError(error, {
            summary: t('notifications.deleteFailedTitle'),
            fallback: t('certificateDesign.content.assignmentRemoveFailed'),
        });
    } finally {
        deletingAssignmentId.value = null;
    }
};
const askDeleteAssignment = (assignment, event) => {
    if (!props.canUpdate || !assignment) return;

    confirm.require({
        target: event?.currentTarget ?? event?.target ?? document.body,
        message: t('certificateDesign.content.confirmRemoveAssignment'),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            text: true,
        },
        acceptProps: {
            label: t('certificateDesign.content.removeAssignment'),
            severity: 'danger',
        },
        accept: () => deleteAssignment(assignment),
    });
};

watch(
    () => `${templateIdentity(props.selectedCenter?.id) ?? 'none'}:${props.selectedAchievementType}`,
    () => {
        resetAssignmentForContext();
        if (!isEditorDirty.value) {
            loadTemplate(effectiveTemplate.value);
        }
    },
    { immediate: true },
);
watch(
    () => normalizedTemplates.value.map((template) => [
        template.id,
        template.name,
        template.is_active,
        JSON.stringify(template.sections),
    ].join(':')).join('|'),
    () => {
        const preferred = normalizedTemplates.value.find((template) => sameId(template.id, preferredTemplateId.value));
        if (preferred) {
            preferredTemplateId.value = null;
            loadTemplate(preferred);
            assignmentForm.value.template_id = preferred.id;

            return;
        }
        if (editorMode.value === 'idle') {
            loadTemplate(effectiveTemplate.value);

            return;
        }
        if (!isEditorDirty.value && selectedTemplateId.value !== null) {
            const refreshed = normalizedTemplates.value.find((template) => sameId(template.id, selectedTemplateId.value));
            loadTemplate(refreshed ?? effectiveTemplate.value);
        }
    },
    { immediate: true },
);
watch(selectedTemplateId, (templateId) => {
    const template = normalizedTemplates.value.find((candidate) => sameId(candidate.id, templateId));
    if (template?.is_active) assignmentForm.value.template_id = template.id;
});
watch(
    () => assignmentForm.value.scope_type,
    (scopeType) => {
        if (!scopeOptions.value.some((option) => option.value === scopeType)) {
            assignmentForm.value.scope_type = 'center';
        }
        assignmentErrors.value = {};
    },
);
watch(
    () => ({
        mode: editorMode.value,
        templateId: templateIdentity(editor.value.id),
        templateName: String(editor.value.name ?? ''),
        sections: normalizePreviewSections(editor.value.sections),
    }),
    (value) => {
        emit('preview-change', value.mode === 'idle'
            ? { templateId: currentEffective.value.template_id, templateName: effectiveTemplate.value?.name ?? '', sections: null }
            : value);
    },
    { deep: true, immediate: true, flush: 'sync' },
);
</script>

<template>
    <section class="space-y-5" aria-labelledby="certificate-content-heading">
        <article class="overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--card) text-(--card-foreground) shadow-(--shadow-sm)">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-(--border) p-5">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[color-mix(in_oklab,var(--accent)_13%,transparent)] text-[var(--accent)]">
                        <i class="pi pi-file-edit text-lg" aria-hidden="true"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 id="certificate-content-heading" class="text-xl font-semibold">{{ t('certificateDesign.content.title') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">{{ t('certificateDesign.content.description') }}</p>
                    </div>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="accentBadgeClass"
                >
                    <i class="pi pi-sitemap text-[0.7rem]" aria-hidden="true"></i>
                    {{ effectiveSourceLabel }}
                </span>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-(--muted-foreground)">{{ t('certificateDesign.content.effectiveTemplate') }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p class="truncate text-lg font-semibold">
                            {{ effectiveTemplate?.name || t('certificateDesign.content.legacyTemplate') }}
                        </p>
                        <span v-if="effectiveTemplate?.is_system" class="rounded-full px-2.5 py-1 text-xs font-bold" :class="accentBadgeClass">
                            {{ t('certificateDesign.content.systemTemplate') }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-(--muted-foreground)">
                        {{ selectedCenter?.center_name || selectedCenter?.name || t('common.na') }}
                        <span aria-hidden="true"> · </span>
                        {{ selectedTypeLabel }}
                    </p>
                </div>

                <Button
                    v-if="canUpdate && selectedCenter && selectedTemplate?.is_active"
                    type="button"
                    icon="pi pi-bolt"
                    :label="t('certificateDesign.content.applyCurrentContext')"
                    size="small"
                    :loading="assigning"
                    @click="applyToCurrentContext"
                />
            </div>
        </article>

        <div
            v-if="requestError"
            class="flex items-start gap-3 rounded-(--radius-base) border border-red-300/70 bg-red-50 p-4 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/35 dark:text-red-100"
            role="alert"
        >
            <i class="pi pi-exclamation-circle mt-0.5 shrink-0" aria-hidden="true"></i>
            <p class="min-w-0 flex-1">{{ requestError }}</p>
            <button type="button" class="shrink-0 rounded p-1 hover:bg-red-100 dark:hover:bg-red-900" :aria-label="t('common.close')" @click="requestError = ''">
                <i class="pi pi-times" aria-hidden="true"></i>
            </button>
        </div>

        <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">{{ t('certificateDesign.content.templateLibrary') }}</h3>
                    <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificateDesign.content.templateLibraryHint') }}</p>
                </div>
                <div v-if="canManageTemplates" class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        icon="pi pi-copy"
                        :label="t('certificateDesign.content.duplicateTemplate')"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="!selectedTemplate || savingTemplate"
                        @click="startDuplicate"
                    />
                    <Button
                        type="button"
                        icon="pi pi-plus"
                        :label="t('certificateDesign.content.newTemplate')"
                        size="small"
                        :disabled="savingTemplate"
                        @click="startCreate"
                    />
                </div>
            </div>

            <div class="relative mt-4">
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-(--muted-foreground)">
                    <i class="pi pi-search text-sm" aria-hidden="true"></i>
                </span>
                <InputText
                    v-model="templateSearch"
                    :placeholder="t('certificateDesign.content.searchTemplates')"
                    class="h-10 w-full"
                    style="padding-left: 2.5rem"
                    dir="rtl"
                />
            </div>

            <div v-if="filteredTemplates.length" class="mt-3 grid max-h-72 gap-2 overflow-y-auto pe-1 sm:grid-cols-2">
                <button
                    v-for="template in filteredTemplates"
                    :key="template.id"
                    type="button"
                    class="group flex min-w-0 items-start gap-3 rounded-xl border p-3 text-start transition"
                    :class="sameId(template.id, selectedTemplateId)
                        ? 'border-[var(--accent)] bg-[color-mix(in_oklab,var(--accent)_8%,transparent)] ring-2 ring-[color-mix(in_oklab,var(--accent)_15%,transparent)]'
                        : 'border-(--border) bg-(--background) hover:border-[color-mix(in_oklab,var(--accent)_45%,var(--border))]'"
                    :aria-pressed="sameId(template.id, selectedTemplateId)"
                    @click="requestTemplateSelection(template, $event)"
                >
                    <span
                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                        :class="template.is_system
                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                            : 'bg-(--muted) text-(--foreground)'"
                    >
                        <i class="pi" :class="template.is_system ? 'pi-verified' : 'pi-file-edit'" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ template.name }}</span>
                        <span class="mt-1 flex flex-wrap gap-1.5">
                            <span v-if="template.is_system" class="rounded-full px-2.5 py-1 text-xs font-bold" :class="accentBadgeClass">
                                {{ t('certificateDesign.content.systemTemplate') }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="template.is_active
                                    ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100'
                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200'"
                            >
                                {{ template.is_active ? t('messageTemplates.active') : t('messageTemplates.inactive') }}
                            </span>
                            <span v-if="template.assignments_count" class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="accentBadgeClass">
                                {{ t('certificateDesign.content.assignmentsCount', { count: template.assignments_count }) }}
                            </span>
                        </span>
                    </span>
                    <span v-if="sameId(template.id, selectedTemplateId)" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[var(--accent)]" aria-hidden="true"></span>
                </button>
            </div>

            <div v-else class="mt-4 rounded-xl border border-dashed border-(--border) bg-(--background) p-6 text-center text-sm text-(--muted-foreground)">
                <i class="pi pi-file mb-2 block text-2xl" aria-hidden="true"></i>
                <p>{{ templateSearch ? t('certificateDesign.content.noMatchingTemplates') : t('certificateDesign.content.noTemplates') }}</p>
            </div>
        </article>

        <article
            v-if="editorMode !== 'idle'"
            class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold">
                            {{ editorMode === 'create' ? t('certificateDesign.content.createTemplate') : t('certificateDesign.content.editTemplate') }}
                        </h3>
                        <span v-if="editor.is_system" class="rounded-full px-2.5 py-1 text-xs font-bold" :class="accentBadgeClass">
                            <i class="pi pi-lock me-1 text-[0.65rem]" aria-hidden="true"></i>
                            {{ t('certificateDesign.content.protectedTemplate') }}
                        </span>
                        <span v-if="isEditorDirty" class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2.5 py-1 text-[0.7rem] font-semibold text-orange-900 dark:bg-orange-950/50 dark:text-orange-100">
                            <span class="h-1.5 w-1.5 rounded-full bg-orange-500" aria-hidden="true"></span>
                            {{ t('certificateDesign.unsaved') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-(--muted-foreground)">{{ t('certificateDesign.content.editorHint') }}</p>
                </div>

                <Button
                    v-if="canManageTemplates && editorMode === 'edit'"
                    type="button"
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    rounded
                    :aria-label="t('certificateDesign.content.deleteTemplate')"
                    :title="editor.is_system ? t('certificateDesign.content.systemCannotDelete') : t('certificateDesign.content.deleteTemplate')"
                    :disabled="editor.is_system || deletingTemplateId !== null || savingTemplate"
                    :loading="sameId(deletingTemplateId, editor.id)"
                    @click="askDeleteTemplate(selectedTemplate, $event)"
                />
            </div>

            <div
                v-if="!canManageTemplates"
                class="mt-4 flex items-start gap-3 rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/35 dark:text-amber-100"
            >
                <i class="pi pi-eye mt-0.5" aria-hidden="true"></i>
                <p>{{ t('certificateDesign.content.templateReadOnly') }}</p>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                <div>
                    <label for="certificate-content-template-name" class="mb-2 block text-sm font-medium">
                        {{ t('certificateDesign.content.templateName') }}
                        <span class="text-red-600">*</span>
                    </label>
                    <InputText
                        id="certificate-content-template-name"
                        v-model="editor.name"
                        class="h-11 w-full"
                        :placeholder="t('certificateDesign.content.templateNamePlaceholder')"
                        :invalid="Boolean(firstError(templateErrors, 'name'))"
                        :disabled="!canManageTemplates || savingTemplate"
                        maxlength="150"
                    />
                    <small v-if="firstError(templateErrors, 'name')" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                        {{ firstError(templateErrors, 'name') }}
                    </small>
                </div>

                <div class="flex min-h-20 items-center justify-between gap-4 rounded-lg border border-(--border) bg-(--background) px-4 py-3 sm:min-w-44">
                    <div>
                        <label for="certificate-content-template-active" class="text-sm font-semibold">{{ t('certificateDesign.content.templateActive') }}</label>
                        <p class="mt-1 text-xs text-(--muted-foreground)">{{ t('certificateDesign.content.templateActiveHint') }}</p>
                    </div>
                    <ToggleSwitch
                        input-id="certificate-content-template-active"
                        v-model="editor.is_active"
                        :disabled="!canManageTemplates || savingTemplate"
                    />
                </div>
            </div>

            <section class="mt-5 rounded-xl border border-[color-mix(in_oklab,var(--accent)_25%,var(--border))] bg-[color-mix(in_oklab,var(--accent)_6%,var(--background))] p-4">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[color-mix(in_oklab,var(--accent)_15%,transparent)] text-[var(--accent)]">
                        <i class="pi pi-code" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h4 class="font-semibold">{{ t('certificateDesign.content.availableVariables') }}</h4>
                        <p class="mt-1 text-xs leading-5 text-(--muted-foreground)">{{ t('certificateDesign.content.variablesHint') }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="variable in variableCatalog"
                        :key="variable.key"
                        type="button"
                        class="group inline-flex items-center gap-2 rounded-lg border border-(--border) bg-(--card) px-2.5 py-2 text-start shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--accent)] hover:shadow"
                        :disabled="!canManageTemplates || savingTemplate"
                        :title="variable.description || variable.sample || variable.token"
                        @click="insertVariable(variable)"
                    >
                        <i class="pi pi-plus-circle text-xs text-[var(--accent)]" aria-hidden="true"></i>
                        <span>
                            <span class="block text-xs font-semibold">{{ variable.label }}</span>
                            <code class="mt-0.5 block text-[0.65rem] text-(--muted-foreground)" dir="ltr">{{ variable.token }}</code>
                        </span>
                    </button>
                </div>
            </section>

            <div
                v-if="unsafeSections.length"
                class="mt-4 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/35 dark:text-red-100"
                role="alert"
            >
                <i class="pi pi-shield mt-0.5 shrink-0" aria-hidden="true"></i>
                <p>{{ t('certificateDesign.content.validation.plainTextOnly') }}</p>
            </div>
            <div
                v-else-if="unknownTokens.length"
                class="mt-4 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/35 dark:text-red-100"
                role="alert"
            >
                <i class="pi pi-exclamation-circle mt-0.5 shrink-0" aria-hidden="true"></i>
                <p>{{ t('certificateDesign.content.validation.unknownVariables', { variables: unknownTokens.join('، ') }) }}</p>
            </div>
            <div
                v-else-if="misplacedVariables.length"
                class="mt-4 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/35 dark:text-red-100"
                role="alert"
            >
                <i class="pi pi-exclamation-circle mt-0.5 shrink-0" aria-hidden="true"></i>
                <p>{{ misplacedVariableMessage }}</p>
            </div>
            <div
                v-else-if="requiredTokenWarnings.length"
                class="mt-4 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/35 dark:text-amber-100"
            >
                <i class="pi pi-info-circle mt-0.5 shrink-0" aria-hidden="true"></i>
                <p>{{ t('certificateDesign.content.validation.recommendedVariables', { variables: requiredTokenWarnings.join('، ') }) }}</p>
            </div>
            <small v-if="firstError(templateErrors, 'sections')" class="mt-2 block text-xs text-red-600 dark:text-red-400">
                {{ firstError(templateErrors, 'sections') }}
            </small>

            <div class="mt-5 grid gap-4">
                <div
                    v-for="section in sectionCatalog"
                    :key="section.key"
                    :data-certificate-template-section="section.key"
                    class="rounded-xl border bg-(--background) p-4 transition"
                    :class="activeSectionKey === section.key
                        ? 'border-[var(--accent)] ring-2 ring-[color-mix(in_oklab,var(--accent)_14%,transparent)]'
                        : (firstError(templateErrors, `sections.${section.key}`) ? 'border-red-500' : 'border-(--border)')"
                >
                    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] px-2 text-[0.7rem] font-bold text-[var(--accent)]">
                                    {{ SECTION_KEYS.indexOf(section.key) + 1 }}
                                </span>
                                <label :for="`certificate-content-${section.key}`" class="text-sm font-semibold">
                                    {{ section.label }}
                                    <span class="text-red-600">*</span>
                                </label>
                                <span
                                    v-for="variableKey in section.requiredVariables"
                                    :key="variableKey"
                                    class="rounded-full bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] px-2.5 py-1 text-[0.65rem] font-semibold text-[var(--accent)]"
                                >
                                    {{ variableCatalog.find((variable) => variable.key === variableKey)?.label || variableKey }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs font-medium leading-5 text-[var(--accent)]">{{ section.placement }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-(--muted-foreground)">{{ section.hint }}</p>
                        </div>
                        <span class="rounded-full bg-(--muted) px-2 py-0.5 text-[0.65rem] tabular-nums text-(--muted-foreground)">
                            {{ Array.from(editor.sections[section.key] || '').length }} / {{ section.maxLength }}
                        </span>
                    </div>
                    <Textarea
                        :id="`certificate-content-${section.key}`"
                        :ref="(instance) => setSectionInput(section.key, instance)"
                        :model-value="editor.sections[section.key]"
                        :rows="section.rows"
                        :maxlength="section.maxLength"
                        class="w-full resize-y leading-7"
                        :invalid="Boolean(firstError(templateErrors, `sections.${section.key}`))"
                        :readonly="!canManageTemplates"
                        :disabled="savingTemplate"
                        dir="rtl"
                        @update:model-value="updateSectionValue(section.key, $event)"
                        @focus="activeSectionKey = section.key"
                        @click="activeSectionKey = section.key"
                    />
                    <small v-if="firstError(templateErrors, `sections.${section.key}`)" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                        {{ firstError(templateErrors, `sections.${section.key}`) }}
                    </small>
                </div>
            </div>

            <div v-if="canManageTemplates" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-(--border) pt-5">
                <p class="text-xs text-(--muted-foreground)">
                    <i class="pi pi-shield me-1 text-emerald-600" aria-hidden="true"></i>
                    {{ t('certificateDesign.content.safeRenderingHint') }}
                </p>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        :label="t('common.cancel')"
                        severity="secondary"
                        text
                        :disabled="savingTemplate"
                        @click="cancelEditing"
                    />
                    <Button
                        type="button"
                        icon="pi pi-save"
                        :label="editorMode === 'create' ? t('certificateDesign.content.createTemplate') : t('certificateDesign.content.saveTemplate')"
                        :loading="savingTemplate"
                        :disabled="!isEditorDirty || unsafeSections.length > 0 || unknownTokens.length > 0 || misplacedVariables.length > 0 || missingRequiredVariables.length > 0"
                        @click="saveTemplate"
                    />
                </div>
            </div>
        </article>

        <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-5 text-(--card-foreground) shadow-(--shadow-sm)">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-(--muted) text-(--foreground)">
                    <i class="pi pi-sitemap" aria-hidden="true"></i>
                </span>
                <div>
                    <h3 class="text-lg font-semibold">{{ t('certificateDesign.content.assignmentTitle') }}</h3>
                    <p class="mt-1 text-sm leading-6 text-(--muted-foreground)">{{ t('certificateDesign.content.assignmentHint') }}</p>
                </div>
            </div>

            <div v-if="activeTemplateOptions.length" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="certificate-content-assignment-template" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.content.assignmentTemplate') }}</label>
                    <Select
                        input-id="certificate-content-assignment-template"
                        v-model="assignmentForm.template_id"
                        :options="activeTemplateOptions"
                        option-label="label"
                        option-value="value"
                        filter
                        fluid
                        :disabled="!canUpdate || assigning"
                    >
                        <template #option="{ option }">
                            <div class="flex items-center gap-2">
                                <i v-if="option.is_system" class="pi pi-verified text-amber-600" aria-hidden="true"></i>
                                <span>{{ option.label }}</span>
                            </div>
                        </template>
                    </Select>
                    <small v-if="firstError(assignmentErrors, 'template_id')" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                        {{ firstError(assignmentErrors, 'template_id') }}
                    </small>
                </div>

                <div v-if="scopeOptions.length > 1">
                    <label for="certificate-content-assignment-scope" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.content.assignmentScope') }}</label>
                    <Select
                        input-id="certificate-content-assignment-scope"
                        v-model="assignmentForm.scope_type"
                        :options="scopeOptions"
                        option-label="label"
                        option-value="value"
                        fluid
                        :disabled="!canUpdate || assigning"
                    />
                </div>
                <div v-else class="rounded-lg border border-(--border) bg-(--background) px-4 py-3">
                    <p class="text-xs font-medium text-(--muted-foreground)">{{ t('certificateDesign.content.assignmentScope') }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ t('certificateDesign.content.scopes.center') }}</p>
                </div>

                <div>
                    <label for="certificate-content-assignment-type" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.content.assignmentAchievementType') }}</label>
                    <Select
                        input-id="certificate-content-assignment-type"
                        v-model="assignmentForm.achievement_type"
                        :options="achievementTypeOptions"
                        option-label="label"
                        option-value="value"
                        fluid
                        :disabled="!canUpdate || assigning"
                    />
                </div>

                <div v-if="assignmentForm.scope_type === 'center'" class="sm:col-span-2">
                    <label for="certificate-content-assignment-center" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.content.assignmentCenter') }}</label>
                    <Select
                        input-id="certificate-content-assignment-center"
                        v-model="assignmentForm.center_id"
                        :options="centerOptions"
                        option-label="center_name"
                        option-value="id"
                        filter
                        fluid
                        :disabled="!canUpdate || assigning"
                    />
                    <small v-if="firstError(assignmentErrors, 'center_id')" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                        {{ firstError(assignmentErrors, 'center_id') }}
                    </small>
                </div>

                <div v-if="assignmentForm.scope_type === 'gender'" class="sm:col-span-2">
                    <label for="certificate-content-assignment-gender" class="mb-2 block text-sm font-medium">{{ t('certificateDesign.content.assignmentGender') }}</label>
                    <Select
                        input-id="certificate-content-assignment-gender"
                        v-model="assignmentForm.student_gender"
                        :options="genderOptions"
                        option-label="label"
                        option-value="value"
                        fluid
                        :disabled="!canUpdate || assigning"
                    />
                    <small v-if="firstError(assignmentErrors, 'student_gender')" class="mt-1 block text-xs text-red-600 dark:text-red-400">
                        {{ firstError(assignmentErrors, 'student_gender') }}
                    </small>
                </div>
            </div>

            <div v-else class="mt-5 rounded-lg border border-dashed border-(--border) bg-(--background) p-4 text-sm text-(--muted-foreground)">
                {{ t('certificateDesign.content.noActiveTemplates') }}
            </div>

            <div
                v-if="exactAssignment"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-cyan-300/70 bg-cyan-50 p-3 text-sm text-cyan-950 dark:border-cyan-800 dark:bg-cyan-950/30 dark:text-cyan-100"
            >
                <p>
                    <i class="pi pi-link me-1" aria-hidden="true"></i>
                    {{ t('certificateDesign.content.existingAssignment', { name: exactAssignmentTemplate?.name || t('common.na') }) }}
                </p>
                <Button
                    v-if="canUpdate"
                    type="button"
                    icon="pi pi-link-slash"
                    :label="t('certificateDesign.content.removeAssignment')"
                    severity="danger"
                    text
                    size="small"
                    :loading="sameId(deletingAssignmentId, exactAssignment.id)"
                    :disabled="assigning || deletingAssignmentId !== null"
                    @click="askDeleteAssignment(exactAssignment, $event)"
                />
            </div>

            <small v-if="firstError(assignmentErrors, 'scope_type') || firstError(assignmentErrors, 'achievement_type')" class="mt-2 block text-xs text-red-600 dark:text-red-400">
                {{ firstError(assignmentErrors, 'scope_type') || firstError(assignmentErrors, 'achievement_type') }}
            </small>

            <div v-if="canUpdate" class="mt-5 flex justify-end">
                <Button
                    type="button"
                    icon="pi pi-link"
                    :label="exactAssignment ? t('certificateDesign.content.updateAssignment') : t('certificateDesign.content.saveAssignment')"
                    :loading="assigning"
                    :disabled="!canSubmitAssignment"
                    @click="saveAssignment"
                />
            </div>
        </article>

        <ConfirmPopup />
    </section>
</template>
