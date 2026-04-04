import { createBlock, createVNode, mergeProps, openBlock, resolveDynamicComponent, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent, ssrRenderVNode } from "vue/server-renderer";
import { useI18n } from "vue-i18n";
import FloatLabel from "primevue/floatlabel";
import InputText from "primevue/inputtext";
//#region resources/js/components/form/FormFieldLabel.vue
var _sfc_main$1 = {
	__name: "FormFieldLabel",
	__ssrInlineRender: true,
	props: {
		forId: {
			type: String,
			required: true
		},
		text: {
			type: String,
			required: true
		},
		required: {
			type: Boolean,
			default: false
		}
	},
	setup(__props) {
		const { t } = useI18n();
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<label${ssrRenderAttrs(mergeProps({
				for: __props.forId,
				class: "inline-flex items-center"
			}, _attrs))}><span>${ssrInterpolate(__props.text)}</span>`);
			if (__props.required) _push(`<span class="ms-1 font-bold text-red-600" aria-hidden="true">*</span>`);
			else _push(`<!---->`);
			if (__props.required) _push(`<span class="sr-only">(${ssrInterpolate(unref(t)("common.required"))})</span>`);
			else _push(`<!---->`);
			_push(`</label>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/form/FormFieldLabel.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/components/form/PrimeFloatField.vue
var _sfc_main = {
	__name: "PrimeFloatField",
	__ssrInlineRender: true,
	props: {
		id: {
			type: String,
			required: true
		},
		label: {
			type: String,
			required: true
		},
		modelValue: {
			type: [String, Number],
			default: ""
		},
		component: {
			type: [Object, String],
			default: InputText
		},
		inputType: {
			type: String,
			default: "text"
		},
		autocomplete: {
			type: String,
			default: void 0
		},
		required: {
			type: Boolean,
			default: false
		},
		invalid: {
			type: Boolean,
			default: false
		},
		error: {
			type: String,
			default: ""
		},
		inputProps: {
			type: Object,
			default: () => ({})
		}
	},
	emits: ["update:modelValue"],
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps({ class: "flex flex-col gap-1" }, _attrs))}>`);
			_push(ssrRenderComponent(unref(FloatLabel), { variant: "on" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						ssrRenderVNode(_push, createVNode(resolveDynamicComponent(__props.component), mergeProps({
							"input-id": __props.id,
							"model-value": __props.modelValue,
							type: __props.inputType,
							autocomplete: __props.autocomplete,
							required: __props.required,
							invalid: __props.invalid,
							fluid: "",
							class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
						}, __props.inputProps, { "onUpdate:modelValue": ($event) => _ctx.$emit("update:modelValue", $event) }), null), _parent, _scopeId);
						_push(ssrRenderComponent(_sfc_main$1, {
							"for-id": __props.id,
							text: __props.label,
							required: __props.required
						}, null, _parent, _scopeId));
					} else return [(openBlock(), createBlock(resolveDynamicComponent(__props.component), mergeProps({
						"input-id": __props.id,
						"model-value": __props.modelValue,
						type: __props.inputType,
						autocomplete: __props.autocomplete,
						required: __props.required,
						invalid: __props.invalid,
						fluid: "",
						class: "h-11 rounded-md border border-(--border) bg-(--background) text-(--foreground) shadow-none"
					}, __props.inputProps, { "onUpdate:modelValue": ($event) => _ctx.$emit("update:modelValue", $event) }), null, 16, [
						"input-id",
						"model-value",
						"type",
						"autocomplete",
						"required",
						"invalid",
						"onUpdate:modelValue"
					])), createVNode(_sfc_main$1, {
						"for-id": __props.id,
						text: __props.label,
						required: __props.required
					}, null, 8, [
						"for-id",
						"text",
						"required"
					])];
				}),
				_: 1
			}, _parent));
			if (__props.error) _push(`<small class="text-sm text-red-600">${ssrInterpolate(__props.error)}</small>`);
			else _push(`<!---->`);
			_push(`</div>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/form/PrimeFloatField.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main$1 as n, _sfc_main as t };
