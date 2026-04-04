import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$2 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { t as _sfc_main$3 } from "./PrimeFloatField-D_Dm9Fud.js";
import { createVNode, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
import { Head, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import Password from "primevue/password";
//#region resources/js/Pages/Admin/Profile/Password.vue
var _sfc_main = {
	__name: "Password",
	__ssrInlineRender: true,
	setup(__props) {
		const { t } = useI18n();
		const form = useForm({
			current_password: "",
			password: "",
			password_confirmation: ""
		});
		const submit = () => {
			form.put("/admin/password", { onSuccess: () => form.reset() });
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("profile.updatePassword") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("profile.updatePassword")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
						_push(`<article class="max-w-3xl rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8"${_scopeId}><h2 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("profile.updatePassword"))}</h2><p class="mt-2 text-base text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("profile.passwordDescription"))}</p><form class="mt-6 grid gap-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, {
							id: "current-password",
							modelValue: unref(form).current_password,
							"onUpdate:modelValue": ($event) => unref(form).current_password = $event,
							label: unref(t)("profile.currentPassword"),
							component: unref(Password),
							"input-type": "password",
							autocomplete: "current-password",
							required: "",
							invalid: Boolean(unref(form).errors.current_password),
							error: unref(form).errors.current_password,
							"input-props": {
								feedback: false,
								toggleMask: true
							}
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							id: "new-password",
							modelValue: unref(form).password,
							"onUpdate:modelValue": ($event) => unref(form).password = $event,
							label: unref(t)("profile.newPassword"),
							component: unref(Password),
							"input-type": "password",
							autocomplete: "new-password",
							required: "",
							invalid: Boolean(unref(form).errors.password),
							error: unref(form).errors.password,
							"input-props": {
								feedback: false,
								toggleMask: true
							}
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							id: "new-password-confirmation",
							modelValue: unref(form).password_confirmation,
							"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
							label: unref(t)("users.confirmPassword"),
							component: unref(Password),
							"input-type": "password",
							autocomplete: "new-password",
							required: "",
							invalid: Boolean(unref(form).errors.password_confirmation),
							error: unref(form).errors.password_confirmation,
							"input-props": {
								feedback: false,
								toggleMask: true
							}
						}, null, _parent, _scopeId));
						_push(`<div class="mt-2 flex justify-end"${_scopeId}>`);
						_push(ssrRenderComponent(unref(Button), {
							type: "submit",
							label: unref(t)("profile.updatePassword"),
							loading: unref(form).processing
						}, null, _parent, _scopeId));
						_push(`</div></form></article></section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [createVNode(_sfc_main$2), createVNode("article", { class: "max-w-3xl rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8" }, [
						createVNode("h2", { class: "text-2xl font-semibold" }, toDisplayString(unref(t)("profile.updatePassword")), 1),
						createVNode("p", { class: "mt-2 text-base text-(--muted-foreground)" }, toDisplayString(unref(t)("profile.passwordDescription")), 1),
						createVNode("form", {
							class: "mt-6 grid gap-4",
							onSubmit: withModifiers(submit, ["prevent"])
						}, [
							createVNode(_sfc_main$3, {
								id: "current-password",
								modelValue: unref(form).current_password,
								"onUpdate:modelValue": ($event) => unref(form).current_password = $event,
								label: unref(t)("profile.currentPassword"),
								component: unref(Password),
								"input-type": "password",
								autocomplete: "current-password",
								required: "",
								invalid: Boolean(unref(form).errors.current_password),
								error: unref(form).errors.current_password,
								"input-props": {
									feedback: false,
									toggleMask: true
								}
							}, null, 8, [
								"modelValue",
								"onUpdate:modelValue",
								"label",
								"component",
								"invalid",
								"error"
							]),
							createVNode(_sfc_main$3, {
								id: "new-password",
								modelValue: unref(form).password,
								"onUpdate:modelValue": ($event) => unref(form).password = $event,
								label: unref(t)("profile.newPassword"),
								component: unref(Password),
								"input-type": "password",
								autocomplete: "new-password",
								required: "",
								invalid: Boolean(unref(form).errors.password),
								error: unref(form).errors.password,
								"input-props": {
									feedback: false,
									toggleMask: true
								}
							}, null, 8, [
								"modelValue",
								"onUpdate:modelValue",
								"label",
								"component",
								"invalid",
								"error"
							]),
							createVNode(_sfc_main$3, {
								id: "new-password-confirmation",
								modelValue: unref(form).password_confirmation,
								"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
								label: unref(t)("users.confirmPassword"),
								component: unref(Password),
								"input-type": "password",
								autocomplete: "new-password",
								required: "",
								invalid: Boolean(unref(form).errors.password_confirmation),
								error: unref(form).errors.password_confirmation,
								"input-props": {
									feedback: false,
									toggleMask: true
								}
							}, null, 8, [
								"modelValue",
								"onUpdate:modelValue",
								"label",
								"component",
								"invalid",
								"error"
							]),
							createVNode("div", { class: "mt-2 flex justify-end" }, [createVNode(unref(Button), {
								type: "submit",
								label: unref(t)("profile.updatePassword"),
								loading: unref(form).processing
							}, null, 8, ["label", "loading"])])
						], 32)
					])])];
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Profile/Password.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
