import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { updatePrimaryPalette } from '@primeuix/themes';
import { useI18n } from 'vue-i18n';
import { useAppToast } from './useAppToast';
import { setI18nLocale } from '../i18n';
import { mode } from './useThemeMode';

const DEFAULT_ACCENT = '#059669';
const SETTINGS_STORAGE_KEY = 'vita_system_settings';

const DEFAULT_TOKENS = {
    light: {
        background: '#ffffff',
        foreground: '#0f172a',
        card: '#fdfdfd',
        cardForeground: '#0f172a',
        mutedForeground: '#6b7280',
        border: '#e8e8e8',
        accent: DEFAULT_ACCENT,
    },
    dark: {
        background: '#010101',
        foreground: '#e2e8f0',
        card: '#0a0a0a',
        cardForeground: '#e2e8f0',
        mutedForeground: '#a3a3a3',
        border: '#242424',
        accent: DEFAULT_ACCENT,
    },
};

const DEFAULT_SETTINGS = {
    brandName: 'Vita',
    brandTagline: 'Internal admin system',
    logoUrl: null,
    logoLightUrl: null,
    logoDarkUrl: null,
    iconUrl: null,
    shape: 'comfortable',
    sidebarBehavior: 'default',
    fontFamily: 'instrument',
    language: 'en',
    direction: 'ltr',
    timezone: 'Asia/Amman',
    dateFormat: 'DD/MM/YYYY',
    timeFormat: 'HH:mm',
    tokens: DEFAULT_TOKENS,
};

const settings = ref(structuredClone(DEFAULT_SETTINGS));
let initialized = false;
let saveTimer = null;
let lastToastAt = 0;
let storageWatchInitialized = false;

const FONT_STACKS = {
    instrument: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
    system: "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
    serif: "ui-serif, Georgia, Cambria, 'Times New Roman', Times, serif",
    mono: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace",
    arabic: "'Noto Sans Arabic', 'Cairo', Tahoma, 'Segoe UI', Arial, sans-serif",
    inter: "'Inter', ui-sans-serif, system-ui, sans-serif",
    poppins: "'Poppins', ui-sans-serif, system-ui, sans-serif",
    manrope: "'Manrope', ui-sans-serif, system-ui, sans-serif",
    cairo: "'Cairo', 'Noto Sans Arabic', Tahoma, 'Segoe UI', Arial, sans-serif",
    tajawal: "'Tajawal', 'Noto Sans Arabic', Tahoma, 'Segoe UI', Arial, sans-serif",
    'ibm-plex-sans': "'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif",
    'source-sans-3': "'Source Sans 3', ui-sans-serif, system-ui, sans-serif",
    nunito: "'Nunito', ui-sans-serif, system-ui, sans-serif",
    merriweather: "'Merriweather', ui-serif, Georgia, Cambria, serif",
    'fira-sans': "'Fira Sans', ui-sans-serif, system-ui, sans-serif",
};

const normalizeHex = (value) => {
    if (!value) return null;

    const cleaned = String(value).trim().replace(/^#/, '');

    if (!/^[0-9A-Fa-f]{6}$/.test(cleaned)) {
        return null;
    }

    return `#${cleaned.toLowerCase()}`;
};

const persistSettingsToStorage = (value) => {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Ignore storage failures and continue with runtime state.
    }
};

const readSettingsFromStorage = () => {
    if (typeof window === 'undefined') return null;

    try {
        const raw = window.localStorage.getItem(SETTINGS_STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
};

const normalizeSettings = (input) => {
    const merged = {
        ...structuredClone(DEFAULT_SETTINGS),
        ...(input ?? {}),
        tokens: {
            light: {
                ...DEFAULT_TOKENS.light,
                ...(input?.tokens?.light ?? {}),
            },
            dark: {
                ...DEFAULT_TOKENS.dark,
                ...(input?.tokens?.dark ?? {}),
            },
        },
    };

    const accent = normalizeHex(merged.tokens.light.accent)
        ?? normalizeHex(merged.tokens.dark.accent)
        ?? DEFAULT_ACCENT;

    merged.tokens.light.accent = accent;
    merged.tokens.dark.accent = accent;
    merged.brandName = String(merged.brandName ?? '').trim() || 'Vita';
    merged.brandTagline = String(merged.brandTagline ?? '').trim();
    const logo = String(merged.logoUrl ?? '').trim() || null;
    merged.logoUrl = logo;
    merged.logoLightUrl = String(merged.logoLightUrl ?? '').trim() || logo;
    merged.logoDarkUrl = String(merged.logoDarkUrl ?? '').trim() || logo;
    merged.iconUrl = String(merged.iconUrl ?? '').trim() || null;
    merged.fontFamily = [
        'instrument',
        'system',
        'serif',
        'mono',
        'arabic',
        'inter',
        'poppins',
        'manrope',
        'cairo',
        'tajawal',
        'ibm-plex-sans',
        'source-sans-3',
        'nunito',
        'merriweather',
        'fira-sans',
    ].includes(merged.fontFamily)
        ? merged.fontFamily
        : 'instrument';
    merged.language = merged.language === 'ar' ? 'ar' : 'en';
    merged.direction = merged.direction === 'rtl' ? 'rtl' : 'ltr';
    merged.shape = ['compact', 'comfortable', 'rounded'].includes(merged.shape)
        ? merged.shape
        : 'comfortable';
    merged.sidebarBehavior = [
        'default',
        'condensed',
        'hidden',
        'small_hover_active',
        'small_hover',
    ].includes(merged.sidebarBehavior)
        ? merged.sidebarBehavior
        : 'default';

    return merged;
};

const toPickerValue = (hexValue) => (hexValue ?? '').replace(/^#/, '');

const hexToRgb = (hex) => {
    const normalized = normalizeHex(hex);
    if (!normalized) return null;

    const value = normalized.slice(1);
    return {
        r: Number.parseInt(value.slice(0, 2), 16),
        g: Number.parseInt(value.slice(2, 4), 16),
        b: Number.parseInt(value.slice(4, 6), 16),
    };
};

const rgbToHex = ({ r, g, b }) =>
    `#${[r, g, b]
        .map((channel) => Math.max(0, Math.min(255, Math.round(channel))).toString(16).padStart(2, '0'))
        .join('')}`;

const mixHex = (baseHex, targetHex, weight) => {
    const base = hexToRgb(baseHex);
    const target = hexToRgb(targetHex);

    if (!base || !target) return baseHex;

    const p = Math.max(0, Math.min(1, weight));

    return rgbToHex({
        r: base.r + (target.r - base.r) * p,
        g: base.g + (target.g - base.g) * p,
        b: base.b + (target.b - base.b) * p,
    });
};

const createPrimaryPalette = (accentHex) => ({
    50: mixHex(accentHex, '#ffffff', 0.9),
    100: mixHex(accentHex, '#ffffff', 0.8),
    200: mixHex(accentHex, '#ffffff', 0.65),
    300: mixHex(accentHex, '#ffffff', 0.5),
    400: mixHex(accentHex, '#ffffff', 0.25),
    500: accentHex,
    600: mixHex(accentHex, '#000000', 0.12),
    700: mixHex(accentHex, '#000000', 0.25),
    800: mixHex(accentHex, '#000000', 0.4),
    900: mixHex(accentHex, '#000000', 0.55),
    950: mixHex(accentHex, '#000000', 0.72),
});

const applyPrimeVueAccent = (accentHex) => {
    if (typeof window === 'undefined') return;

    try {
        updatePrimaryPalette(createPrimaryPalette(accentHex));
    } catch {
        // Keep app tokens functional even if PrimeVue runtime theme update fails.
    }
};

const applyShape = (shape) => {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    root.classList.remove('shape-compact', 'shape-comfortable', 'shape-rounded');
    root.classList.add(`shape-${shape}`);
};

const applyFontFamily = (fontFamily) => {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const safeKey = FONT_STACKS[fontFamily] ? fontFamily : 'instrument';
    root.style.setProperty('--app-font-sans', FONT_STACKS[safeKey]);
};

const applyLocalization = (language, direction) => {
    setI18nLocale(language);

    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    root.lang = language === 'ar' ? 'ar' : 'en';
    root.dir = direction === 'rtl' ? 'rtl' : 'ltr';
};

const applyTokensForMode = (currentMode) => {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const tokenSet = settings.value.tokens[currentMode];
    const effectiveTokens = {
        ...DEFAULT_TOKENS[currentMode],
        accent: tokenSet.accent ?? DEFAULT_TOKENS[currentMode].accent,
    };

    root.style.setProperty('--background', effectiveTokens.background);
    root.style.setProperty('--foreground', effectiveTokens.foreground);
    root.style.setProperty('--card', effectiveTokens.card);
    root.style.setProperty('--card-foreground', effectiveTokens.cardForeground);
    root.style.setProperty('--muted-foreground', effectiveTokens.mutedForeground);
    root.style.setProperty('--border', effectiveTokens.border);
    root.style.setProperty('--accent', effectiveTokens.accent);
    applyPrimeVueAccent(effectiveTokens.accent);
};

const persistSettings = async (appToast, t, notify = true) => {
    try {
        const { data } = await axios.put('/admin/settings/system', settings.value);
        settings.value = normalizeSettings(data.settings);
        persistSettingsToStorage(settings.value);
        applyShape(settings.value.shape);
        applyFontFamily(settings.value.fontFamily);
        applyLocalization(settings.value.language, settings.value.direction);
        applyTokensForMode(mode.value);

        if (notify && Date.now() - lastToastAt > 900) {
            lastToastAt = Date.now();
            appToast.success(t('notifications.settingsSaved'), t('notifications.settingsSavedTitle'), 1800);
        }
    } catch (error) {
        if (notify) {
            appToast.fromAxiosError(error, {
                summary: t('notifications.saveFailedTitle'),
                fallback: t('notifications.saveFailedDetail'),
                life: 2200,
            });
        }
    }
};

export const initSystemSettings = (initialSettings = null) => {
    if (initialized) return;

    settings.value = normalizeSettings(initialSettings ?? readSettingsFromStorage());
    persistSettingsToStorage(settings.value);
    applyShape(settings.value.shape);
    applyFontFamily(settings.value.fontFamily);
    applyLocalization(settings.value.language, settings.value.direction);
    applyTokensForMode(mode.value);

    watch(mode, (nextMode) => {
        applyTokensForMode(nextMode);
    });

    if (!storageWatchInitialized) {
        watch(settings, (nextSettings) => {
            persistSettingsToStorage(nextSettings);
        }, { deep: true });
        storageWatchInitialized = true;
    }

    initialized = true;
};

export const useSystemSettings = () => {
    const { t } = useI18n();
    const appToast = useAppToast();
    const activeTokens = computed(() => settings.value.tokens[mode.value]);

    const saveSettings = ({ immediate = false, notify = true } = {}) => {
        if (saveTimer) {
            clearTimeout(saveTimer);
        }

        if (immediate) {
            persistSettings(appToast, t, notify);
            return;
        }

        saveTimer = setTimeout(() => {
            persistSettings(appToast, t, notify);
        }, 180);
    };

    const setShape = (shape) => {
        settings.value.shape = shape;
        applyShape(shape);
        saveSettings({ immediate: true });
    };

    const setSidebarBehavior = (sidebarBehavior) => {
        settings.value.sidebarBehavior = [
            'default',
            'condensed',
            'hidden',
            'small_hover_active',
            'small_hover',
        ].includes(sidebarBehavior)
            ? sidebarBehavior
            : 'default';
        saveSettings({ immediate: true });
    };

    const setFontFamily = (fontFamily) => {
        settings.value.fontFamily = [
            'instrument',
            'system',
            'serif',
            'mono',
            'arabic',
            'inter',
            'poppins',
            'manrope',
            'cairo',
            'tajawal',
            'ibm-plex-sans',
            'source-sans-3',
            'nunito',
            'merriweather',
            'fira-sans',
        ].includes(fontFamily)
            ? fontFamily
            : 'instrument';
        applyFontFamily(settings.value.fontFamily);
        saveSettings({ immediate: true });
    };

    const setLanguage = (language) => {
        settings.value.language = language === 'ar' ? 'ar' : 'en';
        applyLocalization(settings.value.language, settings.value.direction);
        saveSettings({ immediate: true, notify: false });
    };

    const setDirection = (direction) => {
        settings.value.direction = direction === 'rtl' ? 'rtl' : 'ltr';
        applyLocalization(settings.value.language, settings.value.direction);
        saveSettings({ immediate: true, notify: false });
    };

    const setColorToken = (tokenKey, pickerHex) => {
        const normalized = normalizeHex(pickerHex);
        if (!normalized) return;

        if (tokenKey === 'accent') {
            settings.value.tokens.light.accent = normalized;
            settings.value.tokens.dark.accent = normalized;
        } else {
            settings.value.tokens[mode.value][tokenKey] = normalized;
        }

        applyTokensForMode(mode.value);
        saveSettings();
    };

    const resetCurrentModeColors = () => {
        settings.value.tokens.light.accent = DEFAULT_ACCENT;
        settings.value.tokens.dark.accent = DEFAULT_ACCENT;
        applyTokensForMode(mode.value);
        saveSettings({ immediate: true });
    };

    return {
        settings,
        activeTokens,
        mode,
        toPickerValue,
        setShape,
        setSidebarBehavior,
        setFontFamily,
        setLanguage,
        setDirection,
        setColorToken,
        resetCurrentModeColors,
        saveSettings,
    };
};
