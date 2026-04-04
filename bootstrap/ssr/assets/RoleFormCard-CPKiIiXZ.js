import { n as _sfc_main$2, t as _sfc_main$1 } from "./PrimeFloatField-D_Dm9Fud.js";
import { createVNode, mergeProps, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent } from "vue/server-renderer";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import FloatLabel from "primevue/floatlabel";
import MultiSelect from "primevue/multiselect";
//#region resources/js/components/admin/RoleFormCard.vue
var _sfc_main = {
	__name: "RoleFormCard",
	__ssrInlineRender: true,
	props: {
		form: {
			type: Object,
			required: true
		},
		permissions: {
			type: Array,
			default: () => []
		},
		submitLabel: {
			type: String,
			default: "Save Role"
		}
	},
	emits: ["cancel", "submit"],
	setup(__props, { emit: __emit }) {
		const emit = __emit;
		const { t } = useI18n();
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<article${ssrRenderAttrs(mergeProps({ class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm) sm:p-8" }, _attrs))}><form class="grid gap-4">`);
			_push(ssrRenderComponent(_sfc_main$1, {
				id: "role-name",
				modelValue: __props.form.name,
				"onUpdate:modelValue": ($event) => __props.form.name = $event,
				label: unref(t)("roles.roleName"),
				autocomplete: "off",
				required: "",
				invalid: Boolean(__props.form.errors.name),
				error: __props.form.errors.name
			}, null, _parent));
			_push(`<div class="flex flex-col gap-1">`);
			_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(unref(MultiSelect), {
							"input-id": "role-permissions",
							modelValue: __props.form.permissions,
							"onUpdate:modelValue": ($event) => __props.form.permissions = $event,
							options: __props.permissions,
							"option-label": "name",
							"option-value": "id",
							filter: "",
							display: "chip",
							"max-selected-labels": 4,
							"selected-items-label": unref(t)("common.selectedCount"),
							class: "h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							"for-id": "role-permissions",
							text: unref(t)("roles.permissions")
						}, null, _parent, _scopeId));
					} else return [createVNode(unref(MultiSelect), {
						"input-id": "role-permissions",
						modelValue: __props.form.permissions,
						"onUpdate:modelValue": ($event) => __props.form.permissions = $event,
						options: __props.permissions,
						"option-label": "name",
						"option-value": "id",
						filter: "",
						display: "chip",
						"max-selected-labels": 4,
						"selected-items-label": unref(t)("common.selectedCount"),
						class: "h-11 w-full rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
					}, null, 8, [
						"modelValue",
						"onUpdate:modelValue",
						"options",
						"selected-items-label"
					]), createVNode(_sfc_main$2, {
						"for-id": "role-permissions",
						text: unref(t)("roles.permissions")
					}, null, 8, ["text"])];
				}),
				_: 1
			}, _parent));
			if (__props.form.errors.permissions) _push(`<small class="text-sm text-red-600">${ssrInterpolate(__props.form.errors.permissions)}</small>`);
			else _push(`<!---->`);
			_push(`</div><div class="mt-2 flex justify-end gap-2">`);
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/RoleFormCard.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };
