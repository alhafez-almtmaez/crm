import { n as adminNavItems, t as _sfc_main$2 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$3 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { n as useThemeMode, r as useAppToast, t as useSystemSettings } from "./useSystemSettings-CGKqSGLN.js";
import { t as _sfc_main$4 } from "./PrimeFloatField-D_Dm9Fud.js";
import { Fragment, computed, createBlock, createVNode, mergeProps, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Button from "primevue/button";
import FloatLabel from "primevue/floatlabel";
import Dialog from "primevue/dialog";
import ColorPicker from "primevue/colorpicker";
import Select from "primevue/select";
import SelectButton from "primevue/selectbutton";
//#region resources/js/components/form/ImageUploadModal.vue
var _sfc_main$1 = {
	__name: "ImageUploadModal",
	__ssrInlineRender: true,
	props: {
		visible: {
			type: Boolean,
			default: false
		},
		title: {
			type: String,
			default: ""
		},
		currentUrl: {
			type: String,
			default: ""
		},
		uploadUrl: {
			type: String,
			default: "/admin/settings/brand-assets"
		}
	},
	emits: ["update:visible", "uploaded"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const selectedFile = ref(null);
		const previewUrl = ref("");
		const uploading = ref(false);
		const appToast = useAppToast();
		const { t } = useI18n();
		const resetState = () => {
			selectedFile.value = null;
			previewUrl.value = "";
		};
		const close = () => {
			emit("update:visible", false);
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
			formData.append("file", selectedFile.value);
			uploading.value = true;
			try {
				const { data } = await axios.post(props.uploadUrl, formData, { headers: { "Content-Type": "multipart/form-data" } });
				emit("uploaded", data.url);
				appToast.success(data.message ?? t("uploads.uploadCompleted"));
				close();
			} catch (error) {
				appToast.fromAxiosError(error, {
					summary: t("notifications.uploadFailedTitle"),
					fallback: t("notifications.uploadFailedDetail")
				});
			} finally {
				uploading.value = false;
			}
		};
		watch(() => props.visible, (isVisible) => {
			if (!isVisible) {
				if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
				resetState();
			}
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(unref(Dialog), mergeProps({
				visible: __props.visible,
				modal: "",
				header: __props.title,
				style: { width: "min(32rem, 92vw)" },
				"onUpdate:visible": ($event) => emit("update:visible", $event)
			}, _attrs), {
				footer: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="flex justify-end gap-2"${_scopeId}>`);
						_push(ssrRenderComponent(unref(Button), {
							label: unref(t)("common.cancel"),
							severity: "secondary",
							text: "",
							onClick: close
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(unref(Button), {
							label: unref(t)("uploads.upload"),
							loading: uploading.value,
							disabled: !selectedFile.value,
							onClick: upload
						}, null, _parent, _scopeId));
						_push(`</div>`);
					} else return [createVNode("div", { class: "flex justify-end gap-2" }, [createVNode(unref(Button), {
						label: unref(t)("common.cancel"),
						severity: "secondary",
						text: "",
						onClick: close
					}, null, 8, ["label"]), createVNode(unref(Button), {
						label: unref(t)("uploads.upload"),
						loading: uploading.value,
						disabled: !selectedFile.value,
						onClick: upload
					}, null, 8, [
						"label",
						"loading",
						"disabled"
					])])];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="space-y-4"${_scopeId}><div class="grid gap-3 sm:grid-cols-2"${_scopeId}><div class="space-y-2"${_scopeId}><p class="text-sm font-medium text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("uploads.current"))}</p><div class="flex h-28 items-center justify-center rounded-md border border-(--border) bg-(--background)"${_scopeId}>`);
						if (__props.currentUrl) _push(`<img${ssrRenderAttr("src", __props.currentUrl)}${ssrRenderAttr("alt", unref(t)("uploads.current"))} class="h-full w-full rounded-md object-contain"${_scopeId}>`);
						else _push(`<span class="text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("uploads.noImageSet"))}</span>`);
						_push(`</div></div><div class="space-y-2"${_scopeId}><p class="text-sm font-medium text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("uploads.selected"))}</p><div class="flex h-28 items-center justify-center rounded-md border border-(--border) bg-(--background)"${_scopeId}>`);
						if (previewUrl.value) _push(`<img${ssrRenderAttr("src", previewUrl.value)}${ssrRenderAttr("alt", unref(t)("uploads.selectedPreview"))} class="h-full w-full rounded-md object-contain"${_scopeId}>`);
						else _push(`<span class="text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("uploads.noFileSelected"))}</span>`);
						_push(`</div></div></div><input type="file" accept="image/png,image/jpeg,image/webp" class="block w-full rounded-md border border-(--border) bg-(--background) p-2 text-sm"${_scopeId}><p class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("uploads.allowedFormats"))}</p></div>`);
					} else return [createVNode("div", { class: "space-y-4" }, [
						createVNode("div", { class: "grid gap-3 sm:grid-cols-2" }, [createVNode("div", { class: "space-y-2" }, [createVNode("p", { class: "text-sm font-medium text-(--muted-foreground)" }, toDisplayString(unref(t)("uploads.current")), 1), createVNode("div", { class: "flex h-28 items-center justify-center rounded-md border border-(--border) bg-(--background)" }, [__props.currentUrl ? (openBlock(), createBlock("img", {
							key: 0,
							src: __props.currentUrl,
							alt: unref(t)("uploads.current"),
							class: "h-full w-full rounded-md object-contain"
						}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("span", {
							key: 1,
							class: "text-sm text-(--muted-foreground)"
						}, toDisplayString(unref(t)("uploads.noImageSet")), 1))])]), createVNode("div", { class: "space-y-2" }, [createVNode("p", { class: "text-sm font-medium text-(--muted-foreground)" }, toDisplayString(unref(t)("uploads.selected")), 1), createVNode("div", { class: "flex h-28 items-center justify-center rounded-md border border-(--border) bg-(--background)" }, [previewUrl.value ? (openBlock(), createBlock("img", {
							key: 0,
							src: previewUrl.value,
							alt: unref(t)("uploads.selectedPreview"),
							class: "h-full w-full rounded-md object-contain"
						}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("span", {
							key: 1,
							class: "text-sm text-(--muted-foreground)"
						}, toDisplayString(unref(t)("uploads.noFileSelected")), 1))])])]),
						createVNode("input", {
							type: "file",
							accept: "image/png,image/jpeg,image/webp",
							class: "block w-full rounded-md border border-(--border) bg-(--background) p-2 text-sm",
							onChange: handleFileChange
						}, null, 32),
						createVNode("p", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("uploads.allowedFormats")), 1)
					])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/form/ImageUploadModal.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/Admin/Settings.vue
var _sfc_main = {
	__name: "Settings",
	__ssrInlineRender: true,
	setup(__props) {
		const { mode, setMode } = useThemeMode();
		const { t } = useI18n();
		const { settings, activeTokens, toPickerValue, setShape, setSidebarBehavior, setFontFamily, setLanguage, setDirection, setColorToken, saveSettings } = useSystemSettings();
		const modeOptions = computed(() => [{
			label: t("settings.light"),
			value: "light"
		}, {
			label: t("settings.dark"),
			value: "dark"
		}]);
		const shapeOptions = computed(() => [
			{
				label: t("settings.compact"),
				value: "compact"
			},
			{
				label: t("settings.comfortable"),
				value: "comfortable"
			},
			{
				label: t("settings.rounded"),
				value: "rounded"
			}
		]);
		const sidebarBehaviorOptions = computed(() => [
			{
				label: t("settings.sidebarDefault"),
				value: "default"
			},
			{
				label: t("settings.sidebarCondensed"),
				value: "condensed"
			},
			{
				label: t("settings.sidebarHidden"),
				value: "hidden"
			},
			{
				label: t("settings.sidebarSmallHoverActive"),
				value: "small_hover_active"
			},
			{
				label: t("settings.sidebarSmallHover"),
				value: "small_hover"
			}
		]);
		const fontOptions = [
			{
				label: "Instrument Sans",
				value: "instrument"
			},
			{
				label: "System UI",
				value: "system"
			},
			{
				label: "Inter",
				value: "inter"
			},
			{
				label: "Poppins",
				value: "poppins"
			},
			{
				label: "Manrope",
				value: "manrope"
			},
			{
				label: "IBM Plex Sans",
				value: "ibm-plex-sans"
			},
			{
				label: "Source Sans 3",
				value: "source-sans-3"
			},
			{
				label: "Nunito",
				value: "nunito"
			},
			{
				label: "Fira Sans",
				value: "fira-sans"
			},
			{
				label: "Serif",
				value: "serif"
			},
			{
				label: "Merriweather",
				value: "merriweather"
			},
			{
				label: "Monospace",
				value: "mono"
			},
			{
				label: "Arabic UI",
				value: "arabic"
			},
			{
				label: "Cairo",
				value: "cairo"
			},
			{
				label: "Tajawal",
				value: "tajawal"
			}
		];
		const languageOptions = computed(() => [{
			label: t("settings.english"),
			value: "en"
		}, {
			label: t("settings.arabic"),
			value: "ar"
		}]);
		const directionOptions = computed(() => [{
			label: t("settings.ltr"),
			value: "ltr"
		}, {
			label: t("settings.rtl"),
			value: "rtl"
		}]);
		const timezoneOptions = (() => {
			if (typeof Intl !== "undefined" && typeof Intl.supportedValuesOf === "function") return Intl.supportedValuesOf("timeZone");
			return [
				"UTC",
				"Asia/Amman",
				"Asia/Dubai",
				"Europe/Berlin",
				"Europe/London",
				"America/New_York",
				"America/Los_Angeles"
			];
		})();
		const dateFormatOptions = [
			"DD/MM/YYYY",
			"D/M/YYYY",
			"MM/DD/YYYY",
			"M/D/YYYY",
			"YYYY-MM-DD",
			"DD-MM-YYYY",
			"MM-DD-YYYY",
			"DD.MM.YYYY",
			"MMM D, YYYY",
			"D MMM YYYY",
			"MMMM D, YYYY",
			"D MMMM YYYY"
		];
		const timeFormatOptions = [
			"HH:mm",
			"HH:mm:ss",
			"HH:mm:ss.SSS",
			"hh:mm A",
			"hh:mm:ss A",
			"h:mm A",
			"h:mm:ss A"
		];
		const colorFields = [{
			key: "accent",
			labelKey: "settings.accent"
		}];
		const brandAssetModalVisible = ref(false);
		const brandAssetField = ref("logoLightUrl");
		const modeValue = computed({
			get: () => mode.value,
			set: (value) => setMode(value)
		});
		const shapeValue = computed({
			get: () => settings.value.shape,
			set: (value) => setShape(value)
		});
		const updateField = (field, value) => {
			settings.value[field] = value;
			saveSettings();
		};
		const updateLanguage = (language) => {
			setLanguage(language);
			if (language === "ar") setDirection("rtl");
			if (language === "en") setDirection("ltr");
		};
		const openBrandAssetModal = (field) => {
			brandAssetField.value = field;
			brandAssetModalVisible.value = true;
		};
		const brandAssetModalTitle = computed(() => brandAssetField.value === "iconUrl" ? t("settings.uploadAppIcon") : brandAssetField.value === "logoDarkUrl" ? t("settings.uploadDarkModeLogo") : t("settings.uploadLightModeLogo"));
		const brandAssetCurrentUrl = computed(() => settings.value[brandAssetField.value] ?? "");
		const handleBrandAssetUploaded = (url) => {
			updateField(brandAssetField.value, url);
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("settings.title") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$2, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("settings.title")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, null, null, _parent, _scopeId));
						_push(`<article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><h3 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("settings.branding"))}</h3><p class="mt-2 text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.brandingDescription"))}</p><div class="mt-5 grid gap-4 md:grid-cols-2"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							id: "brand-name",
							"model-value": unref(settings).brandName,
							label: unref(t)("settings.brandName"),
							required: "",
							"onUpdate:modelValue": ($event) => updateField("brandName", $event)
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							id: "brand-tagline",
							"model-value": unref(settings).brandTagline,
							label: unref(t)("settings.brandTagline"),
							"onUpdate:modelValue": ($event) => updateField("brandTagline", $event)
						}, null, _parent, _scopeId));
						_push(`<div class="rounded-md border border-(--border) bg-(--background) p-3"${_scopeId}><p class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.lightModeLogo"))}</p><div class="mt-2 flex items-center justify-between gap-3"${_scopeId}><div class="flex h-12 min-w-0 items-center"${_scopeId}>`);
						if (unref(settings).logoLightUrl) _push(`<img${ssrRenderAttr("src", unref(settings).logoLightUrl)}${ssrRenderAttr("alt", unref(t)("settings.currentLightModeLogo"))} class="h-10 w-auto max-w-48 object-contain"${_scopeId}>`);
						else _push(`<span class="text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.noLogoUploaded"))}</span>`);
						_push(`</div>`);
						_push(ssrRenderComponent(unref(Button), {
							type: "button",
							icon: "pi pi-upload",
							label: unref(t)("settings.uploadLightLogo"),
							size: "small",
							outlined: "",
							onClick: ($event) => openBrandAssetModal("logoLightUrl")
						}, null, _parent, _scopeId));
						_push(`</div><p class="mt-2 text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.recommendedLogo"))}</p></div><div class="rounded-md border border-(--border) bg-(--background) p-3"${_scopeId}><p class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.darkModeLogo"))}</p><div class="mt-2 flex items-center justify-between gap-3"${_scopeId}><div class="flex h-12 min-w-0 items-center"${_scopeId}>`);
						if (unref(settings).logoDarkUrl) _push(`<img${ssrRenderAttr("src", unref(settings).logoDarkUrl)}${ssrRenderAttr("alt", unref(t)("settings.currentDarkModeLogo"))} class="h-10 w-auto max-w-48 object-contain"${_scopeId}>`);
						else _push(`<span class="text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.noLogoUploaded"))}</span>`);
						_push(`</div>`);
						_push(ssrRenderComponent(unref(Button), {
							type: "button",
							icon: "pi pi-upload",
							label: unref(t)("settings.uploadDarkLogo"),
							size: "small",
							outlined: "",
							onClick: ($event) => openBrandAssetModal("logoDarkUrl")
						}, null, _parent, _scopeId));
						_push(`</div><p class="mt-2 text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.recommendedDarkLogo"))}</p></div><div class="rounded-md border border-(--border) bg-(--background) p-3"${_scopeId}><p class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.appIcon"))}</p><div class="mt-2 flex items-center justify-between gap-3"${_scopeId}><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-(--card)"${_scopeId}>`);
						if (unref(settings).iconUrl) _push(`<img${ssrRenderAttr("src", unref(settings).iconUrl)}${ssrRenderAttr("alt", unref(t)("settings.currentAppIcon"))} class="h-12 w-12 rounded-xl object-cover"${_scopeId}>`);
						else _push(`<span class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("common.na"))}</span>`);
						_push(`</div>`);
						_push(ssrRenderComponent(unref(Button), {
							type: "button",
							icon: "pi pi-upload",
							label: unref(t)("settings.uploadIcon"),
							size: "small",
							outlined: "",
							onClick: ($event) => openBrandAssetModal("iconUrl")
						}, null, _parent, _scopeId));
						_push(`</div><p class="mt-2 text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.recommendedIcon"))}</p></div></div></article><article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><h3 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("settings.localization"))}</h3><p class="mt-2 text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.localizationDescription"))}</p><div class="mt-5 grid gap-4 md:grid-cols-2"${_scopeId}><div${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "default-language",
										"model-value": unref(settings).language,
										options: languageOptions.value,
										"option-label": "label",
										"option-value": "value",
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateLanguage($event)
									}, null, _parent, _scopeId));
									_push(`<label for="default-language"${_scopeId}>${ssrInterpolate(unref(t)("settings.defaultLanguage"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "default-language",
									"model-value": unref(settings).language,
									options: languageOptions.value,
									"option-label": "label",
									"option-value": "value",
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => updateLanguage($event)
								}, null, 8, [
									"model-value",
									"options",
									"onUpdate:modelValue"
								]), createVNode("label", { for: "default-language" }, toDisplayString(unref(t)("settings.defaultLanguage")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div><div${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "interface-direction",
										"model-value": unref(settings).direction,
										options: directionOptions.value,
										"option-label": "label",
										"option-value": "value",
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => unref(setDirection)($event)
									}, null, _parent, _scopeId));
									_push(`<label for="interface-direction"${_scopeId}>${ssrInterpolate(unref(t)("settings.interfaceDirection"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "interface-direction",
									"model-value": unref(settings).direction,
									options: directionOptions.value,
									"option-label": "label",
									"option-value": "value",
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => unref(setDirection)($event)
								}, null, 8, [
									"model-value",
									"options",
									"onUpdate:modelValue"
								]), createVNode("label", { for: "interface-direction" }, toDisplayString(unref(t)("settings.interfaceDirection")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></div></article><article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><h3 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("settings.dateTime"))}</h3><p class="mt-2 text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.dateTimeDescription"))}</p><div class="mt-5 grid gap-4 md:grid-cols-3"${_scopeId}><div${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "date-format",
										"model-value": unref(settings).dateFormat,
										options: dateFormatOptions,
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateField("dateFormat", $event)
									}, null, _parent, _scopeId));
									_push(`<label for="date-format"${_scopeId}>${ssrInterpolate(unref(t)("settings.dateFormat"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "date-format",
									"model-value": unref(settings).dateFormat,
									options: dateFormatOptions,
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => updateField("dateFormat", $event)
								}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "date-format" }, toDisplayString(unref(t)("settings.dateFormat")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div><div${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "time-format",
										"model-value": unref(settings).timeFormat,
										options: timeFormatOptions,
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateField("timeFormat", $event)
									}, null, _parent, _scopeId));
									_push(`<label for="time-format"${_scopeId}>${ssrInterpolate(unref(t)("settings.timeFormat"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "time-format",
									"model-value": unref(settings).timeFormat,
									options: timeFormatOptions,
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => updateField("timeFormat", $event)
								}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "time-format" }, toDisplayString(unref(t)("settings.timeFormat")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div><div${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "timezone",
										"model-value": unref(settings).timezone,
										options: unref(timezoneOptions),
										filter: "",
										"filter-placeholder": unref(t)("settings.searchTimezone"),
										"virtual-scroller-options": { itemSize: 38 },
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateField("timezone", $event)
									}, null, _parent, _scopeId));
									_push(`<label for="timezone"${_scopeId}>${ssrInterpolate(unref(t)("settings.timezone"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "timezone",
									"model-value": unref(settings).timezone,
									options: unref(timezoneOptions),
									filter: "",
									"filter-placeholder": unref(t)("settings.searchTimezone"),
									"virtual-scroller-options": { itemSize: 38 },
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => updateField("timezone", $event)
								}, null, 8, [
									"model-value",
									"options",
									"filter-placeholder",
									"onUpdate:modelValue"
								]), createVNode("label", { for: "timezone" }, toDisplayString(unref(t)("settings.timezone")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></div></article><article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><h3 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("settings.appearance"))}</h3><p class="mt-2 text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("settings.appearanceDescription"))}</p><div class="mt-5 grid gap-5"${_scopeId}><div${_scopeId}><p class="mb-2 text-sm font-medium"${_scopeId}>${ssrInterpolate(unref(t)("settings.mode"))}</p>`);
						_push(ssrRenderComponent(unref(SelectButton), {
							modelValue: modeValue.value,
							"onUpdate:modelValue": ($event) => modeValue.value = $event,
							options: modeOptions.value,
							"option-label": "label",
							"option-value": "value"
						}, null, _parent, _scopeId));
						_push(`</div><div${_scopeId}><p class="mb-2 text-sm font-medium"${_scopeId}>${ssrInterpolate(unref(t)("settings.componentShape"))}</p>`);
						_push(ssrRenderComponent(unref(SelectButton), {
							modelValue: shapeValue.value,
							"onUpdate:modelValue": ($event) => shapeValue.value = $event,
							options: shapeOptions.value,
							"option-label": "label",
							"option-value": "value"
						}, null, _parent, _scopeId));
						_push(`</div><div class="max-w-sm"${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "sidebar-behavior",
										"model-value": unref(settings).sidebarBehavior,
										options: sidebarBehaviorOptions.value,
										"option-label": "label",
										"option-value": "value",
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => unref(setSidebarBehavior)($event)
									}, null, _parent, _scopeId));
									_push(`<label for="sidebar-behavior"${_scopeId}>${ssrInterpolate(unref(t)("settings.sidebarBehavior"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "sidebar-behavior",
									"model-value": unref(settings).sidebarBehavior,
									options: sidebarBehaviorOptions.value,
									"option-label": "label",
									"option-value": "value",
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => unref(setSidebarBehavior)($event)
								}, null, 8, [
									"model-value",
									"options",
									"onUpdate:modelValue"
								]), createVNode("label", { for: "sidebar-behavior" }, toDisplayString(unref(t)("settings.sidebarBehavior")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div><div class="max-w-sm"${_scopeId}>`);
						_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(unref(Select), {
										"input-id": "font-family",
										"model-value": unref(settings).fontFamily,
										options: fontOptions,
										"option-label": "label",
										"option-value": "value",
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => unref(setFontFamily)($event)
									}, null, _parent, _scopeId));
									_push(`<label for="font-family"${_scopeId}>${ssrInterpolate(unref(t)("settings.fontFamily"))}</label>`);
								} else return [createVNode(unref(Select), {
									"input-id": "font-family",
									"model-value": unref(settings).fontFamily,
									options: fontOptions,
									"option-label": "label",
									"option-value": "value",
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => unref(setFontFamily)($event)
								}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "font-family" }, toDisplayString(unref(t)("settings.fontFamily")), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div><div${_scopeId}><div class="mb-2 flex items-center justify-between gap-3"${_scopeId}><p class="text-sm font-medium"${_scopeId}>${ssrInterpolate(unref(t)("settings.accentColor", { mode: unref(t)(`settings.${unref(mode)}`) }))}</p></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"${_scopeId}><!--[-->`);
						ssrRenderList(colorFields, (field) => {
							_push(`<div class="rounded-md border border-(--border) bg-[color-mix(in_oklab,var(--card)_86%,var(--background))] p-3"${_scopeId}><p class="mb-2 text-sm font-medium"${_scopeId}>${ssrInterpolate(unref(t)(field.labelKey))}</p><div class="flex items-center gap-3"${_scopeId}>`);
							_push(ssrRenderComponent(unref(ColorPicker), {
								"model-value": unref(toPickerValue)(unref(activeTokens)[field.key]),
								format: "hex",
								"onUpdate:modelValue": ($event) => unref(setColorToken)(field.key, $event)
							}, null, _parent, _scopeId));
							_push(`<span class="text-xs text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(activeTokens)[field.key])}</span></div></div>`);
						});
						_push(`<!--]--></div></div></div></article></section>`);
						_push(ssrRenderComponent(_sfc_main$1, {
							visible: brandAssetModalVisible.value,
							"onUpdate:visible": ($event) => brandAssetModalVisible.value = $event,
							title: brandAssetModalTitle.value,
							"current-url": brandAssetCurrentUrl.value,
							onUploaded: handleBrandAssetUploaded
						}, null, _parent, _scopeId));
					} else return [createVNode("section", { class: "space-y-6" }, [
						createVNode(_sfc_main$3),
						createVNode("article", { class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)" }, [
							createVNode("h3", { class: "text-2xl font-semibold" }, toDisplayString(unref(t)("settings.branding")), 1),
							createVNode("p", { class: "mt-2 text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.brandingDescription")), 1),
							createVNode("div", { class: "mt-5 grid gap-4 md:grid-cols-2" }, [
								createVNode(_sfc_main$4, {
									id: "brand-name",
									"model-value": unref(settings).brandName,
									label: unref(t)("settings.brandName"),
									required: "",
									"onUpdate:modelValue": ($event) => updateField("brandName", $event)
								}, null, 8, [
									"model-value",
									"label",
									"onUpdate:modelValue"
								]),
								createVNode(_sfc_main$4, {
									id: "brand-tagline",
									"model-value": unref(settings).brandTagline,
									label: unref(t)("settings.brandTagline"),
									"onUpdate:modelValue": ($event) => updateField("brandTagline", $event)
								}, null, 8, [
									"model-value",
									"label",
									"onUpdate:modelValue"
								]),
								createVNode("div", { class: "rounded-md border border-(--border) bg-(--background) p-3" }, [
									createVNode("p", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.lightModeLogo")), 1),
									createVNode("div", { class: "mt-2 flex items-center justify-between gap-3" }, [createVNode("div", { class: "flex h-12 min-w-0 items-center" }, [unref(settings).logoLightUrl ? (openBlock(), createBlock("img", {
										key: 0,
										src: unref(settings).logoLightUrl,
										alt: unref(t)("settings.currentLightModeLogo"),
										class: "h-10 w-auto max-w-48 object-contain"
									}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("span", {
										key: 1,
										class: "text-sm text-(--muted-foreground)"
									}, toDisplayString(unref(t)("settings.noLogoUploaded")), 1))]), createVNode(unref(Button), {
										type: "button",
										icon: "pi pi-upload",
										label: unref(t)("settings.uploadLightLogo"),
										size: "small",
										outlined: "",
										onClick: ($event) => openBrandAssetModal("logoLightUrl")
									}, null, 8, ["label", "onClick"])]),
									createVNode("p", { class: "mt-2 text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.recommendedLogo")), 1)
								]),
								createVNode("div", { class: "rounded-md border border-(--border) bg-(--background) p-3" }, [
									createVNode("p", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.darkModeLogo")), 1),
									createVNode("div", { class: "mt-2 flex items-center justify-between gap-3" }, [createVNode("div", { class: "flex h-12 min-w-0 items-center" }, [unref(settings).logoDarkUrl ? (openBlock(), createBlock("img", {
										key: 0,
										src: unref(settings).logoDarkUrl,
										alt: unref(t)("settings.currentDarkModeLogo"),
										class: "h-10 w-auto max-w-48 object-contain"
									}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("span", {
										key: 1,
										class: "text-sm text-(--muted-foreground)"
									}, toDisplayString(unref(t)("settings.noLogoUploaded")), 1))]), createVNode(unref(Button), {
										type: "button",
										icon: "pi pi-upload",
										label: unref(t)("settings.uploadDarkLogo"),
										size: "small",
										outlined: "",
										onClick: ($event) => openBrandAssetModal("logoDarkUrl")
									}, null, 8, ["label", "onClick"])]),
									createVNode("p", { class: "mt-2 text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.recommendedDarkLogo")), 1)
								]),
								createVNode("div", { class: "rounded-md border border-(--border) bg-(--background) p-3" }, [
									createVNode("p", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.appIcon")), 1),
									createVNode("div", { class: "mt-2 flex items-center justify-between gap-3" }, [createVNode("div", { class: "flex h-12 w-12 items-center justify-center rounded-xl bg-(--card)" }, [unref(settings).iconUrl ? (openBlock(), createBlock("img", {
										key: 0,
										src: unref(settings).iconUrl,
										alt: unref(t)("settings.currentAppIcon"),
										class: "h-12 w-12 rounded-xl object-cover"
									}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("span", {
										key: 1,
										class: "text-xs text-(--muted-foreground)"
									}, toDisplayString(unref(t)("common.na")), 1))]), createVNode(unref(Button), {
										type: "button",
										icon: "pi pi-upload",
										label: unref(t)("settings.uploadIcon"),
										size: "small",
										outlined: "",
										onClick: ($event) => openBrandAssetModal("iconUrl")
									}, null, 8, ["label", "onClick"])]),
									createVNode("p", { class: "mt-2 text-xs text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.recommendedIcon")), 1)
								])
							])
						]),
						createVNode("article", { class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)" }, [
							createVNode("h3", { class: "text-2xl font-semibold" }, toDisplayString(unref(t)("settings.localization")), 1),
							createVNode("p", { class: "mt-2 text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.localizationDescription")), 1),
							createVNode("div", { class: "mt-5 grid gap-4 md:grid-cols-2" }, [createVNode("div", null, [createVNode(unref(FloatLabel), { variant: "on" }, {
								default: withCtx(() => [createVNode(unref(Select), {
									"input-id": "default-language",
									"model-value": unref(settings).language,
									options: languageOptions.value,
									"option-label": "label",
									"option-value": "value",
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => updateLanguage($event)
								}, null, 8, [
									"model-value",
									"options",
									"onUpdate:modelValue"
								]), createVNode("label", { for: "default-language" }, toDisplayString(unref(t)("settings.defaultLanguage")), 1)]),
								_: 1
							})]), createVNode("div", null, [createVNode(unref(FloatLabel), { variant: "on" }, {
								default: withCtx(() => [createVNode(unref(Select), {
									"input-id": "interface-direction",
									"model-value": unref(settings).direction,
									options: directionOptions.value,
									"option-label": "label",
									"option-value": "value",
									fluid: "",
									class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
									"onUpdate:modelValue": ($event) => unref(setDirection)($event)
								}, null, 8, [
									"model-value",
									"options",
									"onUpdate:modelValue"
								]), createVNode("label", { for: "interface-direction" }, toDisplayString(unref(t)("settings.interfaceDirection")), 1)]),
								_: 1
							})])])
						]),
						createVNode("article", { class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)" }, [
							createVNode("h3", { class: "text-2xl font-semibold" }, toDisplayString(unref(t)("settings.dateTime")), 1),
							createVNode("p", { class: "mt-2 text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.dateTimeDescription")), 1),
							createVNode("div", { class: "mt-5 grid gap-4 md:grid-cols-3" }, [
								createVNode("div", null, [createVNode(unref(FloatLabel), { variant: "on" }, {
									default: withCtx(() => [createVNode(unref(Select), {
										"input-id": "date-format",
										"model-value": unref(settings).dateFormat,
										options: dateFormatOptions,
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateField("dateFormat", $event)
									}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "date-format" }, toDisplayString(unref(t)("settings.dateFormat")), 1)]),
									_: 1
								})]),
								createVNode("div", null, [createVNode(unref(FloatLabel), { variant: "on" }, {
									default: withCtx(() => [createVNode(unref(Select), {
										"input-id": "time-format",
										"model-value": unref(settings).timeFormat,
										options: timeFormatOptions,
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateField("timeFormat", $event)
									}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "time-format" }, toDisplayString(unref(t)("settings.timeFormat")), 1)]),
									_: 1
								})]),
								createVNode("div", null, [createVNode(unref(FloatLabel), { variant: "on" }, {
									default: withCtx(() => [createVNode(unref(Select), {
										"input-id": "timezone",
										"model-value": unref(settings).timezone,
										options: unref(timezoneOptions),
										filter: "",
										"filter-placeholder": unref(t)("settings.searchTimezone"),
										"virtual-scroller-options": { itemSize: 38 },
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => updateField("timezone", $event)
									}, null, 8, [
										"model-value",
										"options",
										"filter-placeholder",
										"onUpdate:modelValue"
									]), createVNode("label", { for: "timezone" }, toDisplayString(unref(t)("settings.timezone")), 1)]),
									_: 1
								})])
							])
						]),
						createVNode("article", { class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)" }, [
							createVNode("h3", { class: "text-2xl font-semibold" }, toDisplayString(unref(t)("settings.appearance")), 1),
							createVNode("p", { class: "mt-2 text-(--muted-foreground)" }, toDisplayString(unref(t)("settings.appearanceDescription")), 1),
							createVNode("div", { class: "mt-5 grid gap-5" }, [
								createVNode("div", null, [createVNode("p", { class: "mb-2 text-sm font-medium" }, toDisplayString(unref(t)("settings.mode")), 1), createVNode(unref(SelectButton), {
									modelValue: modeValue.value,
									"onUpdate:modelValue": ($event) => modeValue.value = $event,
									options: modeOptions.value,
									"option-label": "label",
									"option-value": "value"
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"options"
								])]),
								createVNode("div", null, [createVNode("p", { class: "mb-2 text-sm font-medium" }, toDisplayString(unref(t)("settings.componentShape")), 1), createVNode(unref(SelectButton), {
									modelValue: shapeValue.value,
									"onUpdate:modelValue": ($event) => shapeValue.value = $event,
									options: shapeOptions.value,
									"option-label": "label",
									"option-value": "value"
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"options"
								])]),
								createVNode("div", { class: "max-w-sm" }, [createVNode(unref(FloatLabel), { variant: "on" }, {
									default: withCtx(() => [createVNode(unref(Select), {
										"input-id": "sidebar-behavior",
										"model-value": unref(settings).sidebarBehavior,
										options: sidebarBehaviorOptions.value,
										"option-label": "label",
										"option-value": "value",
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => unref(setSidebarBehavior)($event)
									}, null, 8, [
										"model-value",
										"options",
										"onUpdate:modelValue"
									]), createVNode("label", { for: "sidebar-behavior" }, toDisplayString(unref(t)("settings.sidebarBehavior")), 1)]),
									_: 1
								})]),
								createVNode("div", { class: "max-w-sm" }, [createVNode(unref(FloatLabel), { variant: "on" }, {
									default: withCtx(() => [createVNode(unref(Select), {
										"input-id": "font-family",
										"model-value": unref(settings).fontFamily,
										options: fontOptions,
										"option-label": "label",
										"option-value": "value",
										fluid: "",
										class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none",
										"onUpdate:modelValue": ($event) => unref(setFontFamily)($event)
									}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "font-family" }, toDisplayString(unref(t)("settings.fontFamily")), 1)]),
									_: 1
								})]),
								createVNode("div", null, [createVNode("div", { class: "mb-2 flex items-center justify-between gap-3" }, [createVNode("p", { class: "text-sm font-medium" }, toDisplayString(unref(t)("settings.accentColor", { mode: unref(t)(`settings.${unref(mode)}`) })), 1)]), createVNode("div", { class: "grid gap-4 sm:grid-cols-2 lg:grid-cols-3" }, [(openBlock(), createBlock(Fragment, null, renderList(colorFields, (field) => {
									return createVNode("div", {
										key: field.key,
										class: "rounded-md border border-(--border) bg-[color-mix(in_oklab,var(--card)_86%,var(--background))] p-3"
									}, [createVNode("p", { class: "mb-2 text-sm font-medium" }, toDisplayString(unref(t)(field.labelKey)), 1), createVNode("div", { class: "flex items-center gap-3" }, [createVNode(unref(ColorPicker), {
										"model-value": unref(toPickerValue)(unref(activeTokens)[field.key]),
										format: "hex",
										"onUpdate:modelValue": ($event) => unref(setColorToken)(field.key, $event)
									}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("span", { class: "text-xs text-(--muted-foreground)" }, toDisplayString(unref(activeTokens)[field.key]), 1)])]);
								}), 64))])])
							])
						])
					]), createVNode(_sfc_main$1, {
						visible: brandAssetModalVisible.value,
						"onUpdate:visible": ($event) => brandAssetModalVisible.value = $event,
						title: brandAssetModalTitle.value,
						"current-url": brandAssetCurrentUrl.value,
						onUploaded: handleBrandAssetUploaded
					}, null, 8, [
						"visible",
						"onUpdate:visible",
						"title",
						"current-url"
					])];
				}),
				_: 1
			}, _parent));
			_push(`<!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Settings.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
