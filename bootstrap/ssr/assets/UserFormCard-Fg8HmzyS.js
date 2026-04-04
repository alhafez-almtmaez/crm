import { n as _sfc_main$2, t as _sfc_main$1 } from "./PrimeFloatField-D_Dm9Fud.js";
import { createVNode, mergeProps, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent } from "vue/server-renderer";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import FloatLabel from "primevue/floatlabel";
import Password from "primevue/password";
import Select from "primevue/select";
//#region resources/js/components/admin/UserFormCard.vue
var _sfc_main = {
	__name: "UserFormCard",
	__ssrInlineRender: true,
	props: {
		description: {
			type: String,
			default: ""
		},
		form: {
			type: Object,
			required: true
		},
		passwordLabel: {
			type: String,
			default: "Password"
		},
		roles: {
			type: Array,
			default: () => []
		},
		requirePassword: {
			type: Boolean,
			default: true
		},
		submitLabel: {
			type: String,
			default: "Save"
		},
		title: {
			type: String,
			default: ""
		}
	},
	emits: ["cancel", "submit"],
	setup(__props, { emit: __emit }) {
		const emit = __emit;
		const { t } = useI18n();
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<article${ssrRenderAttrs(mergeProps({ class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8" }, _attrs))}>`);
			if (__props.title) _push(`<h2 class="text-2xl font-semibold">${ssrInterpolate(__props.title)}</h2>`);
			else _push(`<!---->`);
			if (__props.description) _push(`<p class="mt-3 text-lg text-(--muted-foreground)">${ssrInterpolate(__props.description)}</p>`);
			else _push(`<!---->`);
			_push(`<form class="mt-6 grid gap-4">`);
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "user-name",
				modelValue: __props.form.name,
				"onUpdate:modelValue": ($event) => __props.form.name = $event,
				label: unref(t)("users.name"),
				autocomplete: "name",
				required: "",
				invalid: Boolean(__props.form.errors.name),
				error: __props.form.errors.name
			}, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "user-email",
				modelValue: __props.form.email,
				"onUpdate:modelValue": ($event) => __props.form.email = $event,
				label: unref(t)("auth.email"),
				"input-type": "email",
				autocomplete: "email",
				required: "",
				invalid: Boolean(__props.form.errors.email),
				error: __props.form.errors.email
			}, null, _parent));
			_push(`<div class="flex flex-col gap-1">`);
			_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(unref(Select), {
							"input-id": "user-role",
							modelValue: __props.form.role_id,
							"onUpdate:modelValue": ($event) => __props.form.role_id = $event,
							options: __props.roles,
							"option-label": "name",
							"option-value": "id",
							class: "h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							"for-id": "user-role",
							text: unref(t)("users.role"),
							required: ""
						}, null, _parent, _scopeId));
					} else return [createVNode(unref(Select), {
						"input-id": "user-role",
						modelValue: __props.form.role_id,
						"onUpdate:modelValue": ($event) => __props.form.role_id = $event,
						options: __props.roles,
						"option-label": "name",
						"option-value": "id",
						class: "h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
					}, null, 8, [
						"modelValue",
						"onUpdate:modelValue",
						"options"
					]), createVNode(_sfc_main$2, {
						"for-id": "user-role",
						text: unref(t)("users.role"),
						required: ""
					}, null, 8, ["text"])];
				}),
				_: 1
			}, _parent));
			if (__props.form.errors.role_id) _push(`<small class="text-sm text-red-600">${ssrInterpolate(__props.form.errors.role_id)}</small>`);
			else _push(`<!---->`);
			_push(`</div>`);
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "user-password",
				modelValue: __props.form.password,
				"onUpdate:modelValue": ($event) => __props.form.password = $event,
				label: __props.passwordLabel,
				component: unref(Password),
				"input-type": "password",
				autocomplete: "new-password",
				required: __props.requirePassword,
				invalid: Boolean(__props.form.errors.password),
				error: __props.form.errors.password,
				"input-props": {
					feedback: false,
					toggleMask: true
				}
			}, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "user-password-confirmation",
				modelValue: __props.form.password_confirmation,
				"onUpdate:modelValue": ($event) => __props.form.password_confirmation = $event,
				label: unref(t)("users.confirmPassword"),
				component: unref(Password),
				"input-type": "password",
				autocomplete: "new-password",
				required: __props.requirePassword,
				invalid: Boolean(__props.form.errors.password_confirmation),
				error: __props.form.errors.password_confirmation,
				"input-props": {
					feedback: false,
					toggleMask: true
				}
			}, null, _parent));
			_push(`<div class="mt-2 flex justify-end gap-2">`);
			_push(ssrRenderComponent(unref(Button), {
				type: "button",
				label: unref(t)("common.cancel"),
				severity: "secondary",
				text: "",
				onClick: ($event) => emit("cancel")
			}, null, _parent));
			_push(ssrRenderComponent(unref(Button), {
				type: "submit",
				label: __props.submitLabel,
				loading: __props.form.processing
			}, null, _parent));
			_push(`</div></form></article>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/UserFormCard.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };
