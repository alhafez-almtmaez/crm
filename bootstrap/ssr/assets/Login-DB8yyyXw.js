import { n as useThemeMode, t as useSystemSettings } from "./useSystemSettings-CGKqSGLN.js";
import { t as _sfc_main$1 } from "./PrimeFloatField-D_Dm9Fud.js";
import { computed, unref, useSSRContext } from "vue";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent } from "vue/server-renderer";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import Password from "primevue/password";
import Checkbox from "primevue/checkbox";
//#region resources/js/Pages/Auth/Login.vue
var _sfc_main = {
	__name: "Login",
	__ssrInlineRender: true,
	setup(__props) {
		const form = useForm({
			email: "",
			password: "",
			remember: false
		});
		const page = usePage();
		const { t } = useI18n();
		const { settings } = useSystemSettings();
		const { mode } = useThemeMode();
		const appName = computed(() => settings.value.brandName || page.props.app?.name || t("common.app"));
		const logoUrl = computed(() => {
			if (mode.value === "dark") return settings.value.logoDarkUrl ?? settings.value.logoLightUrl ?? settings.value.logoUrl ?? "";
			return settings.value.logoLightUrl ?? settings.value.logoDarkUrl ?? settings.value.logoUrl ?? "";
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("auth.login") }, null, _parent));
			_push(`<main class="flex min-h-screen items-center justify-center bg-(--background) px-4 py-10 text-(--foreground) sm:px-6"><div class="w-full max-w-md"><div class="mb-5 flex justify-center">`);
			if (logoUrl.value) _push(`<img${ssrRenderAttr("src", logoUrl.value)}${ssrRenderAttr("alt", unref(t)("auth.appLogoAlt", { appName: appName.value }))} class="h-10 w-auto max-w-60 object-contain">`);
			else _push(`<p class="truncate text-3xl font-extrabold tracking-tight text-(--accent)">${ssrInterpolate(appName.value)}</p>`);
			_push(`</div><div class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8"><h1 class="text-2xl font-semibold">${ssrInterpolate(unref(t)("auth.signIn"))}</h1><p class="mt-2 text-sm text-(--muted-foreground)">${ssrInterpolate(unref(t)("auth.useAdminAccount"))}</p><form class="mt-6 space-y-4">`);
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "email",
				modelValue: unref(form).email,
				"onUpdate:modelValue": ($event) => unref(form).email = $event,
				label: unref(t)("auth.email"),
				"input-type": "email",
				autocomplete: "email",
				required: "",
				invalid: Boolean(unref(form).errors.email),
				error: unref(form).errors.email
			}, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "password",
				modelValue: unref(form).password,
				"onUpdate:modelValue": ($event) => unref(form).password = $event,
				label: unref(t)("auth.password"),
				component: unref(Password),
				"input-type": "password",
				autocomplete: "current-password",
				required: "",
				invalid: Boolean(unref(form).errors.password),
				error: unref(form).errors.password,
				"input-props": {
					feedback: false,
					toggleMask: true
				}
			}, null, _parent));
			_push(`<label class="flex items-center gap-2 text-sm">`);
			_push(ssrRenderComponent(unref(Checkbox), {
				modelValue: unref(form).remember,
				"onUpdate:modelValue": ($event) => unref(form).remember = $event,
				binary: "",
				"input-id": "remember"
			}, null, _parent));
			_push(`<span>${ssrInterpolate(unref(t)("auth.rememberMe"))}</span></label>`);
			_push(ssrRenderComponent(unref(Button), {
				type: "submit",
				label: unref(t)("auth.signIn"),
				loading: unref(form).processing,
				fluid: ""
			}, null, _parent));
			_push(`</form></div></div></main><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/Login.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
