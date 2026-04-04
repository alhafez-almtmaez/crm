<script setup>
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ColorPicker from 'primevue/colorpicker';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { adminNavItems } from '../../admin/navItems';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import AdminBreadcrumbs from '../../components/admin/AdminBreadcrumbs.vue';
import ImageUploadModal from '../../components/form/ImageUploadModal.vue';
import PrimeFloatField from '../../components/form/PrimeFloatField.vue';
import { useSystemSettings } from '../../composables/useSystemSettings';
import { useThemeMode } from '../../composables/useThemeMode';

const { mode, setMode } = useThemeMode();
const { t } = useI18n();
const {
    settings,
    activeTokens,
    toPickerValue,
    setShape,
    setSidebarBehavior,
    setFontFamily,
    setLanguage,
    setDirection,
    setColorToken,
    saveSettings,
} = useSystemSettings();

const modeOptions = computed(() => [
    { label: t('settings.light'), value: 'light' },
    { label: t('settings.dark'), value: 'dark' },
]);

const shapeOptions = computed(() => [
    { label: t('settings.compact'), value: 'compact' },
    { label: t('settings.comfortable'), value: 'comfortable' },
    { label: t('settings.rounded'), value: 'rounded' },
]);

const sidebarBehaviorOptions = computed(() => [
    { label: t('settings.sidebarDefault'), value: 'default' },
    { label: t('settings.sidebarCondensed'), value: 'condensed' },
    { label: t('settings.sidebarHidden'), value: 'hidden' },
    { label: t('settings.sidebarSmallHoverActive'), value: 'small_hover_active' },
    { label: t('settings.sidebarSmallHover'), value: 'small_hover' },
]);

const fontOptions = [
    { label: 'Instrument Sans', value: 'instrument' },
    { label: 'System UI', value: 'system' },
    { label: 'Inter', value: 'inter' },
    { label: 'Poppins', value: 'poppins' },
    { label: 'Manrope', value: 'manrope' },
    { label: 'IBM Plex Sans', value: 'ibm-plex-sans' },
    { label: 'Source Sans 3', value: 'source-sans-3' },
    { label: 'Nunito', value: 'nunito' },
    { label: 'Fira Sans', value: 'fira-sans' },
    { label: 'Serif', value: 'serif' },
    { label: 'Merriweather', value: 'merriweather' },
    { label: 'Monospace', value: 'mono' },
    { label: 'Arabic UI', value: 'arabic' },
    { label: 'Cairo', value: 'cairo' },
    { label: 'Tajawal', value: 'tajawal' },
];

const languageOptions = computed(() => [
    { label: t('settings.english'), value: 'en' },
    { label: t('settings.arabic'), value: 'ar' },
]);

const directionOptions = computed(() => [
    { label: t('settings.ltr'), value: 'ltr' },
    { label: t('settings.rtl'), value: 'rtl' },
]);

const timezoneOptions = (() => {
    if (typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function') {
        return Intl.supportedValuesOf('timeZone');
    }

    return [
        'UTC',
        'Asia/Amman',
        'Asia/Dubai',
        'Europe/Berlin',
        'Europe/London',
        'America/New_York',
        'America/Los_Angeles',
    ];
})();

const dateFormatOptions = [
    'DD/MM/YYYY',
    'D/M/YYYY',
    'MM/DD/YYYY',
    'M/D/YYYY',
    'YYYY-MM-DD',
    'DD-MM-YYYY',
    'MM-DD-YYYY',
    'DD.MM.YYYY',
    'MMM D, YYYY',
    'D MMM YYYY',
    'MMMM D, YYYY',
    'D MMMM YYYY',
];

const timeFormatOptions = [
    'HH:mm',
    'HH:mm:ss',
    'HH:mm:ss.SSS',
    'hh:mm A',
    'hh:mm:ss A',
    'h:mm A',
    'h:mm:ss A',
];

const colorFields = [
    { key: 'accent', labelKey: 'settings.accent' },
];
const brandAssetModalVisible = ref(false);
const brandAssetField = ref('logoLightUrl');

const modeValue = computed({
    get: () => mode.value,
    set: (value) => setMode(value),
});

const shapeValue = computed({
    get: () => settings.value.shape,
    set: (value) => setShape(value),
});

const updateField = (field, value) => {
    settings.value[field] = value;
    saveSettings();
};

const updateLanguage = (language) => {
    setLanguage(language);
    if (language === 'ar') {
        setDirection('rtl');
    }

    if (language === 'en') {
        setDirection('ltr');
    }
};

const openBrandAssetModal = (field) => {
    brandAssetField.value = field;
    brandAssetModalVisible.value = true;
};

const brandAssetModalTitle = computed(() => (
    brandAssetField.value === 'iconUrl'
        ? t('settings.uploadAppIcon')
        : brandAssetField.value === 'logoDarkUrl'
            ? t('settings.uploadDarkModeLogo')
            : t('settings.uploadLightModeLogo')
));

const brandAssetCurrentUrl = computed(() => settings.value[brandAssetField.value] ?? '');

const handleBrandAssetUploaded = (url) => {
    updateField(brandAssetField.value, url);
};
</script>

<template>
    <Head :title="t('settings.title')" />

    <AdminLayout :nav-items="adminNavItems" :page-title="t('settings.title')">
        <section class="space-y-6">
            <AdminBreadcrumbs />

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)">
                <h3 class="text-2xl font-semibold">{{ t('settings.branding') }}</h3>
                <p class="mt-2 text-(--muted-foreground)">{{ t('settings.brandingDescription') }}</p>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <PrimeFloatField
                        id="brand-name"
                        :model-value="settings.brandName"
                        :label="t('settings.brandName')"
                        required
                        @update:model-value="updateField('brandName', $event)"
                    />
                    <PrimeFloatField
                        id="brand-tagline"
                        :model-value="settings.brandTagline"
                        :label="t('settings.brandTagline')"
                        @update:model-value="updateField('brandTagline', $event)"
                    />

                    <div class="rounded-md border border-(--border) bg-(--background) p-3">
                        <p class="text-xs text-(--muted-foreground)">{{ t('settings.lightModeLogo') }}</p>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div class="flex h-12 min-w-0 items-center">
                                <img
                                    v-if="settings.logoLightUrl"
                                    :src="settings.logoLightUrl"
                                    :alt="t('settings.currentLightModeLogo')"
                                    class="h-10 w-auto max-w-48 object-contain"
                                />
                                <span v-else class="text-sm text-(--muted-foreground)">{{ t('settings.noLogoUploaded') }}</span>
                            </div>
                            <Button
                                type="button"
                                icon="pi pi-upload"
                                :label="t('settings.uploadLightLogo')"
                                size="small"
                                outlined
                                @click="openBrandAssetModal('logoLightUrl')"
                            />
                        </div>
                        <p class="mt-2 text-xs text-(--muted-foreground)">{{ t('settings.recommendedLogo') }}</p>
                    </div>

                    <div class="rounded-md border border-(--border) bg-(--background) p-3">
                        <p class="text-xs text-(--muted-foreground)">{{ t('settings.darkModeLogo') }}</p>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div class="flex h-12 min-w-0 items-center">
                                <img
                                    v-if="settings.logoDarkUrl"
                                    :src="settings.logoDarkUrl"
                                    :alt="t('settings.currentDarkModeLogo')"
                                    class="h-10 w-auto max-w-48 object-contain"
                                />
                                <span v-else class="text-sm text-(--muted-foreground)">{{ t('settings.noLogoUploaded') }}</span>
                            </div>
                            <Button
                                type="button"
                                icon="pi pi-upload"
                                :label="t('settings.uploadDarkLogo')"
                                size="small"
                                outlined
                                @click="openBrandAssetModal('logoDarkUrl')"
                            />
                        </div>
                        <p class="mt-2 text-xs text-(--muted-foreground)">{{ t('settings.recommendedDarkLogo') }}</p>
                    </div>

                    <div class="rounded-md border border-(--border) bg-(--background) p-3">
                        <p class="text-xs text-(--muted-foreground)">{{ t('settings.appIcon') }}</p>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-(--card)">
                                <img
                                    v-if="settings.iconUrl"
                                    :src="settings.iconUrl"
                                    :alt="t('settings.currentAppIcon')"
                                    class="h-12 w-12 rounded-xl object-cover"
                                />
                                <span v-else class="text-xs text-(--muted-foreground)">{{ t('common.na') }}</span>
                            </div>
                            <Button
                                type="button"
                                icon="pi pi-upload"
                                :label="t('settings.uploadIcon')"
                                size="small"
                                outlined
                                @click="openBrandAssetModal('iconUrl')"
                            />
                        </div>
                        <p class="mt-2 text-xs text-(--muted-foreground)">{{ t('settings.recommendedIcon') }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)">
                <h3 class="text-2xl font-semibold">{{ t('settings.localization') }}</h3>
                <p class="mt-2 text-(--muted-foreground)">{{ t('settings.localizationDescription') }}</p>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="default-language"
                                :model-value="settings.language"
                                :options="languageOptions"
                                option-label="label"
                                option-value="value"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="updateLanguage($event)"
                            />
                            <label for="default-language">{{ t('settings.defaultLanguage') }}</label>
                        </FloatLabel>
                    </div>

                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="interface-direction"
                                :model-value="settings.direction"
                                :options="directionOptions"
                                option-label="label"
                                option-value="value"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="setDirection($event)"
                            />
                            <label for="interface-direction">{{ t('settings.interfaceDirection') }}</label>
                        </FloatLabel>
                    </div>
                </div>
            </article>

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)">
                <h3 class="text-2xl font-semibold">{{ t('settings.dateTime') }}</h3>
                <p class="mt-2 text-(--muted-foreground)">{{ t('settings.dateTimeDescription') }}</p>

                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="date-format"
                                :model-value="settings.dateFormat"
                                :options="dateFormatOptions"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="updateField('dateFormat', $event)"
                            />
                            <label for="date-format">{{ t('settings.dateFormat') }}</label>
                        </FloatLabel>
                    </div>

                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="time-format"
                                :model-value="settings.timeFormat"
                                :options="timeFormatOptions"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="updateField('timeFormat', $event)"
                            />
                            <label for="time-format">{{ t('settings.timeFormat') }}</label>
                        </FloatLabel>
                    </div>

                    <div>
                        <FloatLabel variant="on">
                            <Select
                                input-id="timezone"
                                :model-value="settings.timezone"
                                :options="timezoneOptions"
                                filter
                                :filter-placeholder="t('settings.searchTimezone')"
                                :virtual-scroller-options="{ itemSize: 38 }"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="updateField('timezone', $event)"
                            />
                            <label for="timezone">{{ t('settings.timezone') }}</label>
                        </FloatLabel>
                    </div>
                </div>
            </article>

            <article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)">
                <h3 class="text-2xl font-semibold">{{ t('settings.appearance') }}</h3>
                <p class="mt-2 text-(--muted-foreground)">{{ t('settings.appearanceDescription') }}</p>

                <div class="mt-5 grid gap-5">
                    <div>
                        <p class="mb-2 text-sm font-medium">{{ t('settings.mode') }}</p>
                        <SelectButton v-model="modeValue" :options="modeOptions" option-label="label" option-value="value" />
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium">{{ t('settings.componentShape') }}</p>
                        <SelectButton v-model="shapeValue" :options="shapeOptions" option-label="label" option-value="value" />
                    </div>

                    <div class="max-w-sm">
                        <FloatLabel variant="on">
                            <Select
                                input-id="sidebar-behavior"
                                :model-value="settings.sidebarBehavior"
                                :options="sidebarBehaviorOptions"
                                option-label="label"
                                option-value="value"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="setSidebarBehavior($event)"
                            />
                            <label for="sidebar-behavior">{{ t('settings.sidebarBehavior') }}</label>
                        </FloatLabel>
                    </div>

                    <div class="max-w-sm">
                        <FloatLabel variant="on">
                            <Select
                                input-id="font-family"
                                :model-value="settings.fontFamily"
                                :options="fontOptions"
                                option-label="label"
                                option-value="value"
                                fluid
                                class="h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
                                @update:model-value="setFontFamily($event)"
                            />
                            <label for="font-family">{{ t('settings.fontFamily') }}</label>
                        </FloatLabel>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-sm font-medium">{{ t('settings.accentColor', { mode: t(`settings.${mode}`) }) }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="field in colorFields"
                                :key="field.key"
                                class="rounded-md border border-(--border) bg-[color-mix(in_oklab,var(--card)_86%,var(--background))] p-3"
                            >
                                <p class="mb-2 text-sm font-medium">{{ t(field.labelKey) }}</p>
                                <div class="flex items-center gap-3">
                                    <ColorPicker
                                        :model-value="toPickerValue(activeTokens[field.key])"
                                        format="hex"
                                        @update:model-value="setColorToken(field.key, $event)"
                                    />
                                    <span class="text-xs text-(--muted-foreground)">{{ activeTokens[field.key] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <ImageUploadModal
            v-model:visible="brandAssetModalVisible"
            :title="brandAssetModalTitle"
            :current-url="brandAssetCurrentUrl"
            @uploaded="handleBrandAssetUploaded"
        />
    </AdminLayout>
</template>
