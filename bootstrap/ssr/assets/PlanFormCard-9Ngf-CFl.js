import { t as _sfc_main$1 } from "./PrimeFloatField-D_Dm9Fud.js";
import { mergeProps, unref, useSSRContext } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent } from "vue/server-renderer";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
//#region resources/js/components/admin/PlanFormCard.vue
var _sfc_main = {
	__name: "PlanFormCard",
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
				id: "plan-name",
				modelValue: __props.form.name,
				"onUpdate:modelValue": ($event) => __props.form.name = $event,
				label: unref(t)("plans.planName"),
				autocomplete: "off",
				required: "",
				invalid: Boolean(__props.form.errors.name),
				error: __props.form.errors.name
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/PlanFormCard.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };
