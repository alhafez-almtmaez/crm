import { r as useAppToast } from "./useSystemSettings-CGKqSGLN.js";
import { Fragment, computed, createBlock, createCommentVNode, createVNode, mergeProps, onBeforeUnmount, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Button from "primevue/button";
import Column from "primevue/column";
import PrimeDataTable from "primevue/datatable";
import FloatLabel from "primevue/floatlabel";
import InputText from "primevue/inputtext";
//#region resources/js/components/admin/DataTable.vue
var _sfc_main = {
	__name: "DataTable",
	__ssrInlineRender: true,
	props: {
		columns: {
			type: Array,
			default: () => []
		},
		createLabel: {
			type: String,
			default: ""
		},
		showCreate: {
			type: Boolean,
			default: true
		},
		showActions: {
			type: Boolean,
			default: true
		},
		showHistory: {
			type: Boolean,
			default: false
		},
		currentPage: {
			type: Number,
			default: 1
		},
		loading: {
			type: Boolean,
			default: false
		},
		rows: {
			type: Array,
			default: () => []
		},
		rowsPerPage: {
			type: Number,
			default: 10
		},
		searchLabel: {
			type: String,
			default: ""
		},
		search: {
			type: String,
			default: ""
		},
		tableTitle: {
			type: String,
			default: ""
		},
		totalRecords: {
			type: Number,
			default: 0
		},
		sortField: {
			type: String,
			default: null
		},
		sortOrder: {
			type: Number,
			default: null
		},
		emptyMessage: {
			type: String,
			default: ""
		}
	},
	emits: [
		"create",
		"delete",
		"edit",
		"history",
		"pageChange",
		"sortChange",
		"update:search"
	],
	setup(__props, { emit: __emit }) {
		const { t } = useI18n();
		const props = __props;
		const emit = __emit;
		const resolvedCreateLabel = () => props.createLabel || t("common.create");
		const resolvedSearchLabel = () => props.searchLabel || t("common.search");
		const resolvedTableTitle = () => props.tableTitle || t("common.records");
		const resolvedEmptyMessage = () => props.emptyMessage || t("common.noRecords");
		const handlePage = (event) => {
			emit("pageChange", {
				page: event.page + 1,
				rows: event.rows
			});
		};
		const handleSort = (event) => {
			emit("sortChange", {
				sortField: event.sortField ?? null,
				sortOrder: event.sortOrder ?? null
			});
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<article${ssrRenderAttrs(mergeProps({ class: "rounded-(--radius-base) border border-(--border) bg-(--card) p-4 text-(--card-foreground) shadow-(--shadow-sm) sm:p-6" }, _attrs))}><div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><h3 class="text-xl font-semibold sm:text-2xl">${ssrInterpolate(resolvedTableTitle())}</h3><div class="flex flex-col gap-2 sm:flex-row sm:items-center">`);
			_push(ssrRenderComponent(unref(FloatLabel), {
				variant: "on",
				class: "w-full sm:w-72"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(unref(InputText), {
							"input-id": "table-search",
							"model-value": __props.search,
							class: "h-11 w-full rounded-md border border-(--border) bg-(--background) text-base text-(--foreground) shadow-none",
							"onUpdate:modelValue": ($event) => emit("update:search", $event)
						}, null, _parent, _scopeId));
						_push(`<label for="table-search"${_scopeId}>${ssrInterpolate(resolvedSearchLabel())}</label>`);
					} else return [createVNode(unref(InputText), {
						"input-id": "table-search",
						"model-value": __props.search,
						class: "h-11 w-full rounded-md border border-(--border) bg-(--background) text-base text-(--foreground) shadow-none",
						"onUpdate:modelValue": ($event) => emit("update:search", $event)
					}, null, 8, ["model-value", "onUpdate:modelValue"]), createVNode("label", { for: "table-search" }, toDisplayString(resolvedSearchLabel()), 1)];
				}),
				_: 1
			}, _parent));
			if (__props.showCreate) _push(ssrRenderComponent(unref(Button), {
				label: resolvedCreateLabel(),
				icon: "pi pi-plus",
				size: "small",
				class: "h-11 px-4 text-base font-semibold",
				onClick: ($event) => emit("create")
			}, null, _parent));
			else _push(`<!---->`);
			_push(`</div></div>`);
			_push(ssrRenderComponent(unref(PrimeDataTable), {
				value: __props.rows,
				loading: __props.loading,
				"data-key": "id",
				paginator: "",
				lazy: "",
				"sort-mode": "single",
				"removable-sort": "",
				rows: __props.rowsPerPage,
				first: (__props.currentPage - 1) * __props.rowsPerPage,
				"total-records": __props.totalRecords,
				"sort-field": __props.sortField,
				"sort-order": __props.sortOrder,
				"rows-per-page-options": [
					10,
					20,
					50
				],
				"table-style": "min-width: 50rem",
				onPage: handlePage,
				onSort: handleSort
			}, {
				empty: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<div class="py-8 text-center text-sm text-(--muted-foreground)"${_scopeId}>${ssrInterpolate(resolvedEmptyMessage())}</div>`);
					else return [createVNode("div", { class: "py-8 text-center text-sm text-(--muted-foreground)" }, toDisplayString(resolvedEmptyMessage()), 1)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<!--[-->`);
						ssrRenderList(__props.columns, (column) => {
							_push(ssrRenderComponent(unref(Column), {
								key: column.field,
								field: column.field,
								header: column.header,
								sortable: Boolean(column.sortable),
								"sort-field": column.sortField ?? column.field
							}, null, _parent, _scopeId));
						});
						_push(`<!--]-->`);
						if (__props.showActions) _push(ssrRenderComponent(unref(Column), {
							header: unref(t)("common.actions"),
							style: { width: __props.showHistory ? "220px" : "170px" }
						}, {
							body: withCtx(({ data }, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<div class="flex gap-2"${_scopeId}>`);
									if (__props.showHistory) _push(ssrRenderComponent(unref(Button), {
										size: "small",
										severity: "secondary",
										icon: "pi pi-history",
										onClick: ($event) => emit("history", data)
									}, null, _parent, _scopeId));
									else _push(`<!---->`);
									_push(ssrRenderComponent(unref(Button), {
										size: "small",
										severity: "secondary",
										icon: "pi pi-pencil",
										onClick: ($event) => emit("edit", data)
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(unref(Button), {
										size: "small",
										severity: "danger",
										icon: "pi pi-trash",
										onClick: ($event) => emit("delete", {
											data,
											event: $event
										})
									}, null, _parent, _scopeId));
									_push(`</div>`);
								} else return [createVNode("div", { class: "flex gap-2" }, [
									__props.showHistory ? (openBlock(), createBlock(unref(Button), {
										key: 0,
										size: "small",
										severity: "secondary",
										icon: "pi pi-history",
										onClick: ($event) => emit("history", data)
									}, null, 8, ["onClick"])) : createCommentVNode("", true),
									createVNode(unref(Button), {
										size: "small",
										severity: "secondary",
										icon: "pi pi-pencil",
										onClick: ($event) => emit("edit", data)
									}, null, 8, ["onClick"]),
									createVNode(unref(Button), {
										size: "small",
										severity: "danger",
										icon: "pi pi-trash",
										onClick: ($event) => emit("delete", {
											data,
											event: $event
										})
									}, null, 8, ["onClick"])
								])];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
					} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.columns, (column) => {
						return openBlock(), createBlock(unref(Column), {
							key: column.field,
							field: column.field,
							header: column.header,
							sortable: Boolean(column.sortable),
							"sort-field": column.sortField ?? column.field
						}, null, 8, [
							"field",
							"header",
							"sortable",
							"sort-field"
						]);
					}), 128)), __props.showActions ? (openBlock(), createBlock(unref(Column), {
						key: 0,
						header: unref(t)("common.actions"),
						style: { width: __props.showHistory ? "220px" : "170px" }
					}, {
						body: withCtx(({ data }) => [createVNode("div", { class: "flex gap-2" }, [
							__props.showHistory ? (openBlock(), createBlock(unref(Button), {
								key: 0,
								size: "small",
								severity: "secondary",
								icon: "pi pi-history",
								onClick: ($event) => emit("history", data)
							}, null, 8, ["onClick"])) : createCommentVNode("", true),
							createVNode(unref(Button), {
								size: "small",
								severity: "secondary",
								icon: "pi pi-pencil",
								onClick: ($event) => emit("edit", data)
							}, null, 8, ["onClick"]),
							createVNode(unref(Button), {
								size: "small",
								severity: "danger",
								icon: "pi pi-trash",
								onClick: ($event) => emit("delete", {
									data,
									event: $event
								})
							}, null, 8, ["onClick"])
						])]),
						_: 1
					}, 8, ["header", "style"])) : createCommentVNode("", true)];
				}),
				_: 1
			}, _parent));
			_push(`</article>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/admin/DataTable.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
//#region resources/js/composables/useServerTable.js
var useServerTable = ({ endpoint, defaultSortBy = "id", defaultSortDir = "desc", initialPerPage = 10, debounceMs = 300, extraParams = () => ({}) } = {}) => {
	const { t } = useI18n();
	const appToast = useAppToast();
	const loading = ref(false);
	const rows = ref([]);
	const totalRecords = ref(0);
	const currentPage = ref(1);
	const rowsPerPage = ref(initialPerPage);
	const search = ref("");
	const sortBy = ref(defaultSortBy);
	const sortDir = ref(defaultSortDir);
	const tableSortOrder = computed(() => sortDir.value === "asc" ? 1 : -1);
	let searchTimeoutId = null;
	const fetchRows = async () => {
		loading.value = true;
		try {
			const { data } = await axios.get(endpoint, { params: {
				page: currentPage.value,
				per_page: rowsPerPage.value,
				search: search.value,
				sort_by: sortBy.value,
				sort_dir: sortDir.value,
				...extraParams()
			} });
			rows.value = data.data;
			totalRecords.value = data.meta.total;
			currentPage.value = data.meta.current_page;
			rowsPerPage.value = data.meta.per_page;
		} catch (error) {
			appToast.fromAxiosError(error, {
				summary: t("notifications.loadFailedTitle"),
				fallback: t("notifications.loadFailedDetail")
			});
		} finally {
			loading.value = false;
		}
	};
	const onPageChange = async ({ page, rows: nextRows }) => {
		currentPage.value = page;
		rowsPerPage.value = nextRows;
		await fetchRows();
	};
	const onSortChange = async ({ sortField, sortOrder }) => {
		if (!sortField || !sortOrder) {
			sortBy.value = defaultSortBy;
			sortDir.value = defaultSortDir;
		} else {
			sortBy.value = sortField;
			sortDir.value = sortOrder === 1 ? "asc" : "desc";
		}
		currentPage.value = 1;
		await fetchRows();
	};
	watch(search, () => {
		currentPage.value = 1;
		if (searchTimeoutId) window.clearTimeout(searchTimeoutId);
		searchTimeoutId = window.setTimeout(() => {
			fetchRows();
		}, debounceMs);
	});
	onBeforeUnmount(() => {
		if (searchTimeoutId) window.clearTimeout(searchTimeoutId);
	});
	return {
		loading,
		rows,
		totalRecords,
		currentPage,
		rowsPerPage,
		search,
		sortBy,
		sortDir,
		tableSortOrder,
		fetchRows,
		onPageChange,
		onSortChange
	};
};
//#endregion
export { _sfc_main as n, useServerTable as t };
