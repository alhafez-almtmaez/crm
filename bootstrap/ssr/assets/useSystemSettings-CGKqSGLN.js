import { t as setI18nLocale } from "../ssr.js";
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import { updatePrimaryPalette } from "@primeuix/themes";
import { useToast } from "primevue/usetoast";
//#region resources/js/composables/useAppToast.js
var extractErrorDetail = (error, fallback) => {
	const firstValidationMessage = Object.values(error?.response?.data?.errors ?? {}).flat().find((message) => typeof message === "string" && message.length > 0);
	return error?.response?.data?.message ?? firstValidationMessage ?? error?.message ?? fallback;
};
var useAppToast = () => {
	const { t } = useI18n();
	const toast = useToast();
	const push = ({ severity = "info", summary = t("common.info"), detail = "", life = 2400 }) => {
		toast.add({
			severity,
			summary,
			detail,
			life
		});
	};
	const success = (detail, summary = t("common.success"), life = 2200) => {
		push({
			severity: "success",
			summary,
			detail,
			life
		});
	};
	const error = (detail, summary = t("common.error"), life = 2800) => {
		push({
			severity: "error",
			summary,
			detail,
			life
		});
	};
	const info = (detail, summary = t("common.info"), life = 2200) => {
		push({
			severity: "info",
			summary,
			detail,
			life
		});
	};
	const fromAxiosError = (err, { summary = t("notifications.requestFailedTitle"), fallback = t("notifications.requestFailedDetail"), life = 2800 } = {}) => {
		error(extractErrorDetail(err, fallback), summary, life);
	};
	return {
		push,
		success,
		error,
		info,
		fromAxiosError
	};
};
//#endregion
//#region resources/js/composables/useThemeMode.js
var STORAGE_KEY = "vita_theme_mode";
var mode = ref("light");
var applyMode = (value) => {
	if (typeof document === "undefined") return;
	const root = document.documentElement;
	root.classList.remove("light", "dark");
	root.classList.add(value);
	mode.value = value;
};
var useThemeMode = () => {
	const setMode = (value) => {
		const next = value === "dark" ? "dark" : "light";
		applyMode(next);
		if (typeof window !== "undefined") window.localStorage.setItem(STORAGE_KEY, next);
	};
	const toggleMode = () => {
		setMode(mode.value === "dark" ? "light" : "dark");
	};
	return {
		mode,
		setMode,
		toggleMode
	};
};
//#endregion
//#region resources/js/composables/useSystemSettings.js
var DEFAULT_ACCENT = "#059669";
var SETTINGS_STORAGE_KEY = "vita_system_settings";
var DEFAULT_TOKENS = {
	light: {
		background: "#ffffff",
		foreground: "#0f172a",
		card: "#fdfdfd",
		cardForeground: "#0f172a",
		mutedForeground: "#6b7280",
		border: "#e8e8e8",
		accent: DEFAULT_ACCENT
	},
	dark: {
		background: "#010101",
		foreground: "#e2e8f0",
		card: "#0a0a0a",
		cardForeground: "#e2e8f0",
		mutedForeground: "#a3a3a3",
		border: "#242424",
		accent: DEFAULT_ACCENT
	}
};
var DEFAULT_SETTINGS = {
	brandName: "Vita",
	brandTagline: "Internal admin system",
	logoUrl: null,
	logoLightUrl: null,
	logoDarkUrl: null,
	iconUrl: null,
	shape: "comfortable",
	sidebarBehavior: "default",
	fontFamily: "instrument",
	language: "en",
	direction: "ltr",
	timezone: "Asia/Amman",
	dateFormat: "DD/MM/YYYY",
	timeFormat: "HH:mm",
	tokens: DEFAULT_TOKENS
};
var settings = ref(structuredClone(DEFAULT_SETTINGS));
var saveTimer = null;
var lastToastAt = 0;
var FONT_STACKS = {
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
	"ibm-plex-sans": "'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif",
	"source-sans-3": "'Source Sans 3', ui-sans-serif, system-ui, sans-serif",
	nunito: "'Nunito', ui-sans-serif, system-ui, sans-serif",
	merriweather: "'Merriweather', ui-serif, Georgia, Cambria, serif",
	"fira-sans": "'Fira Sans', ui-sans-serif, system-ui, sans-serif"
};
var normalizeHex = (value) => {
	if (!value) return null;
	const cleaned = String(value).trim().replace(/^#/, "");
	if (!/^[0-9A-Fa-f]{6}$/.test(cleaned)) return null;
	return `#${cleaned.toLowerCase()}`;
};
var persistSettingsToStorage = (value) => {
	if (typeof window === "undefined") return;
	try {
		window.localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(value));
	} catch {}
};
var normalizeSettings = (input) => {
	const merged = {
		...structuredClone(DEFAULT_SETTINGS),
		...input ?? {},
		tokens: {
			light: {
				...DEFAULT_TOKENS.light,
				...input?.tokens?.light ?? {}
			},
			dark: {
				...DEFAULT_TOKENS.dark,
				...input?.tokens?.dark ?? {}
			}
		}
	};
	const accent = normalizeHex(merged.tokens.light.accent) ?? normalizeHex(merged.tokens.dark.accent) ?? DEFAULT_ACCENT;
	merged.tokens.light.accent = accent;
	merged.tokens.dark.accent = accent;
	merged.brandName = String(merged.brandName ?? "").trim() || "Vita";
	merged.brandTagline = String(merged.brandTagline ?? "").trim();
	const logo = String(merged.logoUrl ?? "").trim() || null;
	merged.logoUrl = logo;
	merged.logoLightUrl = String(merged.logoLightUrl ?? "").trim() || logo;
	merged.logoDarkUrl = String(merged.logoDarkUrl ?? "").trim() || logo;
	merged.iconUrl = String(merged.iconUrl ?? "").trim() || null;
	merged.fontFamily = [
		"instrument",
		"system",
		"serif",
		"mono",
		"arabic",
		"inter",
		"poppins",
		"manrope",
		"cairo",
		"tajawal",
		"ibm-plex-sans",
		"source-sans-3",
		"nunito",
		"merriweather",
		"fira-sans"
	].includes(merged.fontFamily) ? merged.fontFamily : "instrument";
	merged.language = merged.language === "ar" ? "ar" : "en";
	merged.direction = merged.direction === "rtl" ? "rtl" : "ltr";
	merged.shape = [
		"compact",
		"comfortable",
		"rounded"
	].includes(merged.shape) ? merged.shape : "comfortable";
	merged.sidebarBehavior = [
		"default",
		"condensed",
		"hidden",
		"small_hover_active",
		"small_hover"
	].includes(merged.sidebarBehavior) ? merged.sidebarBehavior : "default";
	return merged;
};
var toPickerValue = (hexValue) => (hexValue ?? "").replace(/^#/, "");
var hexToRgb = (hex) => {
	const normalized = normalizeHex(hex);
	if (!normalized) return null;
	const value = normalized.slice(1);
	return {
		r: Number.parseInt(value.slice(0, 2), 16),
		g: Number.parseInt(value.slice(2, 4), 16),
		b: Number.parseInt(value.slice(4, 6), 16)
	};
};
var rgbToHex = ({ r, g, b }) => `#${[
	r,
	g,
	b
].map((channel) => Math.max(0, Math.min(255, Math.round(channel))).toString(16).padStart(2, "0")).join("")}`;
var mixHex = (baseHex, targetHex, weight) => {
	const base = hexToRgb(baseHex);
	const target = hexToRgb(targetHex);
	if (!base || !target) return baseHex;
	const p = Math.max(0, Math.min(1, weight));
	return rgbToHex({
		r: base.r + (target.r - base.r) * p,
		g: base.g + (target.g - base.g) * p,
		b: base.b + (target.b - base.b) * p
	});
};
var createPrimaryPalette = (accentHex) => ({
	50: mixHex(accentHex, "#ffffff", .9),
	100: mixHex(accentHex, "#ffffff", .8),
	200: mixHex(accentHex, "#ffffff", .65),
	300: mixHex(accentHex, "#ffffff", .5),
	400: mixHex(accentHex, "#ffffff", .25),
	500: accentHex,
	600: mixHex(accentHex, "#000000", .12),
	700: mixHex(accentHex, "#000000", .25),
	800: mixHex(accentHex, "#000000", .4),
	900: mixHex(accentHex, "#000000", .55),
	950: mixHex(accentHex, "#000000", .72)
});
var applyPrimeVueAccent = (accentHex) => {
	if (typeof window === "undefined") return;
	try {
		updatePrimaryPalette(createPrimaryPalette(accentHex));
	} catch {}
};
var applyShape = (shape) => {
	if (typeof document === "undefined") return;
	const root = document.documentElement;
	root.classList.remove("shape-compact", "shape-comfortable", "shape-rounded");
	root.classList.add(`shape-${shape}`);
};
var applyFontFamily = (fontFamily) => {
	if (typeof document === "undefined") return;
	const root = document.documentElement;
	const safeKey = FONT_STACKS[fontFamily] ? fontFamily : "instrument";
	root.style.setProperty("--app-font-sans", FONT_STACKS[safeKey]);
};
var applyLocalization = (language, direction) => {
	setI18nLocale(language);
	if (typeof document === "undefined") return;
	const root = document.documentElement;
	root.lang = language === "ar" ? "ar" : "en";
	root.dir = direction === "rtl" ? "rtl" : "ltr";
};
var applyTokensForMode = (currentMode) => {
	if (typeof document === "undefined") return;
	const root = document.documentElement;
	const tokenSet = settings.value.tokens[currentMode];
	const effectiveTokens = {
		...DEFAULT_TOKENS[currentMode],
		accent: tokenSet.accent ?? DEFAULT_TOKENS[currentMode].accent
	};
	root.style.setProperty("--background", effectiveTokens.background);
	root.style.setProperty("--foreground", effectiveTokens.foreground);
	root.style.setProperty("--card", effectiveTokens.card);
	root.style.setProperty("--card-foreground", effectiveTokens.cardForeground);
	root.style.setProperty("--muted-foreground", effectiveTokens.mutedForeground);
	root.style.setProperty("--border", effectiveTokens.border);
	root.style.setProperty("--accent", effectiveTokens.accent);
	applyPrimeVueAccent(effectiveTokens.accent);
};
var persistSettings = async (appToast, t, notify = true) => {
	try {
		const { data } = await axios.put("/admin/settings/system", settings.value);
		settings.value = normalizeSettings(data.settings);
		persistSettingsToStorage(settings.value);
		applyShape(settings.value.shape);
		applyFontFamily(settings.value.fontFamily);
		applyLocalization(settings.value.language, settings.value.direction);
		applyTokensForMode(mode.value);
		if (notify && Date.now() - lastToastAt > 900) {
			lastToastAt = Date.now();
			appToast.success(t("notifications.settingsSaved"), t("notifications.settingsSavedTitle"), 1800);
		}
	} catch (error) {
		if (notify) appToast.fromAxiosError(error, {
			summary: t("notifications.saveFailedTitle"),
			fallback: t("notifications.saveFailedDetail"),
			life: 2200
		});
	}
};
var useSystemSettings = () => {
	const { t } = useI18n();
	const appToast = useAppToast();
	const activeTokens = computed(() => settings.value.tokens[mode.value]);
	const saveSettings = ({ immediate = false, notify = true } = {}) => {
		if (saveTimer) clearTimeout(saveTimer);
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
			"default",
			"condensed",
			"hidden",
			"small_hover_active",
			"small_hover"
		].includes(sidebarBehavior) ? sidebarBehavior : "default";
		saveSettings({ immediate: true });
	};
	const setFontFamily = (fontFamily) => {
		settings.value.fontFamily = [
			"instrument",
			"system",
			"serif",
			"mono",
			"arabic",
			"inter",
			"poppins",
			"manrope",
			"cairo",
			"tajawal",
			"ibm-plex-sans",
			"source-sans-3",
			"nunito",
			"merriweather",
			"fira-sans"
		].includes(fontFamily) ? fontFamily : "instrument";
		applyFontFamily(settings.value.fontFamily);
		saveSettings({ immediate: true });
	};
	const setLanguage = (language) => {
		settings.value.language = language === "ar" ? "ar" : "en";
		applyLocalization(settings.value.language, settings.value.direction);
		saveSettings({
			immediate: true,
			notify: false
		});
	};
	const setDirection = (direction) => {
		settings.value.direction = direction === "rtl" ? "rtl" : "ltr";
		applyLocalization(settings.value.language, settings.value.direction);
		saveSettings({
			immediate: true,
			notify: false
		});
	};
	const setColorToken = (tokenKey, pickerHex) => {
		const normalized = normalizeHex(pickerHex);
		if (!normalized) return;
		if (tokenKey === "accent") {
			settings.value.tokens.light.accent = normalized;
			settings.value.tokens.dark.accent = normalized;
		} else settings.value.tokens[mode.value][tokenKey] = normalized;
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
		saveSettings
	};
};
//#endregion
export { useThemeMode as n, useAppToast as r, useSystemSettings as t };
