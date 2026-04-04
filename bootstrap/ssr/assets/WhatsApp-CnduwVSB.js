import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$2 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { r as useAppToast } from "./useSystemSettings-CGKqSGLN.js";
import { computed, createBlock, createVNode, onBeforeUnmount, onMounted, openBlock, ref, toDisplayString, unref, useSSRContext, vModelText, watch, withCtx, withDirectives } from "vue";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent } from "vue/server-renderer";
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Button from "primevue/button";
//#region resources/js/Pages/Admin/WhatsApp.vue
var _sfc_main = {
	__name: "WhatsApp",
	__ssrInlineRender: true,
	props: {
		device: {
			type: Object,
			default: () => null
		},
		qr: {
			type: String,
			default: null
		},
		apiConfigured: {
			type: Boolean,
			default: false
		}
	},
	setup(__props) {
		const { t } = useI18n();
		const appToast = useAppToast();
		const props = __props;
		const deviceId = computed(() => props.device?.id ?? null);
		const deviceName = computed(() => props.device?.name ?? t("whatsapp.deviceFallback"));
		const qrImage = ref(props.qr ?? null);
		const phone = ref("");
		const message = ref("");
		const sending = ref(false);
		const deleting = ref(false);
		const refreshing = ref(false);
		let replayIntervalId = null;
		const hasQr = computed(() => Boolean(qrImage.value));
		const canSend = computed(() => props.apiConfigured && !sending.value && phone.value.trim() !== "" && message.value.trim() !== "");
		const stopReplayPolling = () => {
			if (replayIntervalId) {
				window.clearInterval(replayIntervalId);
				replayIntervalId = null;
			}
		};
		const replayScan = async (silent = false) => {
			if (!deviceId.value || !props.apiConfigured) return;
			refreshing.value = true;
			try {
				const { data } = await axios.get(`/admin/whatsapp/${deviceId.value}/replay-scan`);
				const hadQr = qrImage.value !== null;
				qrImage.value = data?.qr ?? null;
				if (hadQr && data?.connected) appToast.success(t("whatsapp.connected"));
			} catch (error) {
				if (!silent) appToast.fromAxiosError(error, {
					summary: t("notifications.requestFailedTitle"),
					fallback: t("whatsapp.refreshFailed")
				});
			} finally {
				refreshing.value = false;
			}
		};
		const startReplayPolling = () => {
			if (typeof window === "undefined" || replayIntervalId || !hasQr.value || !deviceId.value) return;
			replayIntervalId = window.setInterval(() => {
				replayScan(true);
			}, 3e4);
		};
		const sendMessage = async () => {
			if (!deviceId.value || !canSend.value) return;
			sending.value = true;
			try {
				const { data } = await axios.post(`/admin/whatsapp/${deviceId.value}/send`, {
					phone: phone.value.trim(),
					message: message.value.trim()
				});
				phone.value = "";
				message.value = "";
				appToast.success(data?.message ?? t("whatsapp.sendSuccess"));
			} catch (error) {
				appToast.fromAxiosError(error, {
					summary: t("notifications.requestFailedTitle"),
					fallback: t("whatsapp.sendFailed")
				});
			} finally {
				sending.value = false;
			}
		};
		const deleteDevice = async () => {
			if (!deviceId.value || deleting.value) return;
			if (!window.confirm(t("whatsapp.deleteConfirm", { name: deviceName.value }))) return;
			deleting.value = true;
			try {
				const { data } = await axios.delete(`/admin/whatsapp/${deviceId.value}`);
				appToast.success(data?.message ?? t("whatsapp.deviceDeleted"));
				window.location.reload();
			} catch (error) {
				appToast.fromAxiosError(error, {
					summary: t("notifications.deleteFailedTitle"),
					fallback: t("whatsapp.deleteFailed")
				});
			} finally {
				deleting.value = false;
			}
		};
		watch(() => props.qr, (next) => {
			qrImage.value = next ?? null;
		});
		watch(hasQr, (next) => {
			if (next) {
				startReplayPolling();
				return;
			}
			stopReplayPolling();
		}, { immediate: true });
		onMounted(() => {
			if (hasQr.value) startReplayPolling();
		});
		onBeforeUnmount(() => {
			stopReplayPolling();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("whatsapp.title") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("whatsapp.title")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
						_push(`<article class="rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)"${_scopeId}><div class="flex flex-wrap items-center justify-between gap-3"${_scopeId}><div${_scopeId}><h2 class="text-3xl font-semibold tracking-tight"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.title"))}</h2><p class="mt-2 text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.description"))}</p></div>`);
						_push(ssrRenderComponent(unref(Button), {
							type: "button",
							icon: "pi pi-trash",
							severity: "danger",
							outlined: "",
							label: unref(t)("whatsapp.deleteDevice"),
							loading: deleting.value,
							onClick: deleteDevice
						}, null, _parent, _scopeId));
						_push(`</div>`);
						if (!__props.apiConfigured) _push(`<div class="mt-6 rounded-md border border-red-300/60 bg-red-50 px-4 py-3 text-red-800"${_scopeId}><p class="font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.apiMissing"))}</p><p class="mt-1 text-sm"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.apiMissingHelp"))}</p></div>`);
						else if (hasQr.value) {
							_push(`<div class="mt-6 grid gap-6 lg:grid-cols-2"${_scopeId}><div${_scopeId}><h3 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.instructionsTitle"))}</h3><ol class="mt-4 list-decimal space-y-2 ps-5 text-(--muted-foreground)"${_scopeId}><li${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.step1"))}</li><li${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.step2"))}</li><li${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.step3"))}</li><li class="font-semibold text-(--foreground)"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.step4"))}</li></ol></div><div class="rounded-md border border-(--border) bg-(--background) p-5"${_scopeId}><div class="flex items-center justify-between gap-3"${_scopeId}><p class="text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.scanHint"))}</p>`);
							_push(ssrRenderComponent(unref(Button), {
								type: "button",
								size: "small",
								outlined: "",
								icon: "pi pi-refresh",
								label: unref(t)("whatsapp.refreshNow"),
								loading: refreshing.value,
								onClick: ($event) => replayScan(false)
							}, null, _parent, _scopeId));
							_push(`</div><div class="mt-4 flex justify-center"${_scopeId}><img${ssrRenderAttr("src", qrImage.value)} alt="QR code" class="h-64 w-64 rounded-md border border-(--border) object-contain"${_scopeId}></div></div></div>`);
						} else {
							_push(`<div class="mt-6 space-y-4"${_scopeId}><div class="rounded-md border border-emerald-300/60 bg-emerald-50 px-4 py-3 text-emerald-800"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.connected"))}</div><div class="grid gap-4 md:grid-cols-2"${_scopeId}><div${_scopeId}><label for="wa-phone" class="mb-2 block text-sm font-medium"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.phone"))}</label><input id="wa-phone"${ssrRenderAttr("value", phone.value)} type="tel" class="w-full rounded-md border border-(--border) bg-(--background) px-3 py-2"${ssrRenderAttr("placeholder", unref(t)("whatsapp.phonePlaceholder"))}${_scopeId}></div><div${_scopeId}><label for="wa-message" class="mb-2 block text-sm font-medium"${_scopeId}>${ssrInterpolate(unref(t)("whatsapp.message"))}</label><input id="wa-message"${ssrRenderAttr("value", message.value)} type="text" class="w-full rounded-md border border-(--border) bg-(--background) px-3 py-2"${ssrRenderAttr("placeholder", unref(t)("whatsapp.messagePlaceholder"))}${_scopeId}></div></div><div class="flex justify-end"${_scopeId}>`);
							_push(ssrRenderComponent(unref(Button), {
								type: "button",
								icon: "pi pi-send",
								label: unref(t)("whatsapp.send"),
								disabled: !canSend.value,
								loading: sending.value,
								onClick: sendMessage
							}, null, _parent, _scopeId));
							_push(`</div></div>`);
						}
						_push(`</article></section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [createVNode(_sfc_main$2), createVNode("article", { class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-6 text-(--card-foreground) shadow-(--shadow-sm)" }, [createVNode("div", { class: "flex flex-wrap items-center justify-between gap-3" }, [createVNode("div", null, [createVNode("h2", { class: "text-3xl font-semibold tracking-tight" }, toDisplayString(unref(t)("whatsapp.title")), 1), createVNode("p", { class: "mt-2 text-(--muted-foreground)" }, toDisplayString(unref(t)("whatsapp.description")), 1)]), createVNode(unref(Button), {
						type: "button",
						icon: "pi pi-trash",
						severity: "danger",
						outlined: "",
						label: unref(t)("whatsapp.deleteDevice"),
						loading: deleting.value,
						onClick: deleteDevice
					}, null, 8, ["label", "loading"])]), !__props.apiConfigured ? (openBlock(), createBlock("div", {
						key: 0,
						class: "mt-6 rounded-md border border-red-300/60 bg-red-50 px-4 py-3 text-red-800"
					}, [createVNode("p", { class: "font-semibold" }, toDisplayString(unref(t)("whatsapp.apiMissing")), 1), createVNode("p", { class: "mt-1 text-sm" }, toDisplayString(unref(t)("whatsapp.apiMissingHelp")), 1)])) : hasQr.value ? (openBlock(), createBlock("div", {
						key: 1,
						class: "mt-6 grid gap-6 lg:grid-cols-2"
					}, [createVNode("div", null, [createVNode("h3", { class: "text-2xl font-semibold" }, toDisplayString(unref(t)("whatsapp.instructionsTitle")), 1), createVNode("ol", { class: "mt-4 list-decimal space-y-2 ps-5 text-(--muted-foreground)" }, [
						createVNode("li", null, toDisplayString(unref(t)("whatsapp.step1")), 1),
						createVNode("li", null, toDisplayString(unref(t)("whatsapp.step2")), 1),
						createVNode("li", null, toDisplayString(unref(t)("whatsapp.step3")), 1),
						createVNode("li", { class: "font-semibold text-(--foreground)" }, toDisplayString(unref(t)("whatsapp.step4")), 1)
					])]), createVNode("div", { class: "rounded-md border border-(--border) bg-(--background) p-5" }, [createVNode("div", { class: "flex items-center justify-between gap-3" }, [createVNode("p", { class: "text-sm text-(--muted-foreground)" }, toDisplayString(unref(t)("whatsapp.scanHint")), 1), createVNode(unref(Button), {
						type: "button",
						size: "small",
						outlined: "",
						icon: "pi pi-refresh",
						label: unref(t)("whatsapp.refreshNow"),
						loading: refreshing.value,
						onClick: ($event) => replayScan(false)
					}, null, 8, [
						"label",
						"loading",
						"onClick"
					])]), createVNode("div", { class: "mt-4 flex justify-center" }, [createVNode("img", {
						src: qrImage.value,
						alt: "QR code",
						class: "h-64 w-64 rounded-md border border-(--border) object-contain"
					}, null, 8, ["src"])])])])) : (openBlock(), createBlock("div", {
						key: 2,
						class: "mt-6 space-y-4"
					}, [
						createVNode("div", { class: "rounded-md border border-emerald-300/60 bg-emerald-50 px-4 py-3 text-emerald-800" }, toDisplayString(unref(t)("whatsapp.connected")), 1),
						createVNode("div", { class: "grid gap-4 md:grid-cols-2" }, [createVNode("div", null, [createVNode("label", {
							for: "wa-phone",
							class: "mb-2 block text-sm font-medium"
						}, toDisplayString(unref(t)("whatsapp.phone")), 1), withDirectives(createVNode("input", {
							id: "wa-phone",
							"onUpdate:modelValue": ($event) => phone.value = $event,
							type: "tel",
							class: "w-full rounded-md border border-(--border) bg-(--background) px-3 py-2",
							placeholder: unref(t)("whatsapp.phonePlaceholder")
						}, null, 8, ["onUpdate:modelValue", "placeholder"]), [[vModelText, phone.value]])]), createVNode("div", null, [createVNode("label", {
							for: "wa-message",
							class: "mb-2 block text-sm font-medium"
						}, toDisplayString(unref(t)("whatsapp.message")), 1), withDirectives(createVNode("input", {
							id: "wa-message",
							"onUpdate:modelValue": ($event) => message.value = $event,
							type: "text",
							class: "w-full rounded-md border border-(--border) bg-(--background) px-3 py-2",
							placeholder: unref(t)("whatsapp.messagePlaceholder")
						}, null, 8, ["onUpdate:modelValue", "placeholder"]), [[vModelText, message.value]])])]),
						createVNode("div", { class: "flex justify-end" }, [createVNode(unref(Button), {
							type: "button",
							icon: "pi pi-send",
							label: unref(t)("whatsapp.send"),
							disabled: !canSend.value,
							loading: sending.value,
							onClick: sendMessage
						}, null, 8, [
							"label",
							"disabled",
							"loading"
						])])
					]))])])];
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/WhatsApp.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
