import { n as adminNavItems, t as _sfc_main$1 } from "./AdminLayout-XMnZ2yke.js";
import { t as _sfc_main$2 } from "./AdminBreadcrumbs-Ddp11j0e.js";
import { r as useAppToast } from "./useSystemSettings-CGKqSGLN.js";
import { n as _sfc_main$3, t as useServerTable } from "./useServerTable-Cr3UKBMR.js";
import { t as _sfc_main$4 } from "./EntityActivityDrawer-DRL4EbK_.js";
import { computed, createVNode, onMounted, ref, unref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { Head, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import ConfirmPopup from "primevue/confirmpopup";
import { useConfirm } from "primevue/useconfirm";
//#region resources/js/Pages/Admin/Users.vue
var _sfc_main = {
	__name: "Users",
	__ssrInlineRender: true,
	setup(__props) {
		const confirm = useConfirm();
		const appToast = useAppToast();
		const { t } = useI18n();
		const { loading, rows: users, totalRecords, currentPage, rowsPerPage, search, sortBy, tableSortOrder, fetchRows: fetchUsers, onPageChange: handlePageChange, onSortChange: handleSortChange } = useServerTable({
			endpoint: "/admin/users/records",
			defaultSortBy: "id",
			defaultSortDir: "desc"
		});
		const historyVisible = ref(false);
		const historyEndpoint = ref("");
		const historyEntityName = ref("");
		const columns = computed(() => [
			{
				field: "id",
				header: t("common.id"),
				sortable: true
			},
			{
				field: "name",
				header: t("users.name"),
				sortable: true
			},
			{
				field: "email",
				header: t("auth.email"),
				sortable: true
			},
			{
				field: "role_name",
				header: t("users.role")
			},
			{
				field: "created_at_formatted",
				header: t("users.createdAt"),
				sortable: true,
				sortField: "created_at"
			}
		]);
		const openCreate = () => {
			router.get("/admin/users/create");
		};
		const openEdit = (user) => {
			router.get("/admin/users/" + user.id + "/edit");
		};
		const deleteUser = async (user) => {
			try {
				const { data } = await axios.delete("/admin/users/" + user.id);
				appToast.success(data?.message ?? t("notifications.userDeleted"));
				await fetchUsers();
			} catch (error) {
				appToast.fromAxiosError(error, {
					summary: t("notifications.deleteFailedTitle"),
					fallback: t("notifications.deleteUserFailed")
				});
			}
		};
		const askDeleteUser = ({ data: user, event }) => {
			const target = event?.currentTarget ?? event?.target ?? document.body;
			confirm.require({
				target,
				message: t("users.deleteConfirm", { name: user.name }),
				icon: "pi pi-exclamation-triangle",
				rejectProps: {
					label: t("common.cancel"),
					severity: "secondary",
					text: true
				},
				acceptProps: {
					label: t("users.deleteUser"),
					severity: "danger"
				},
				accept: () => {
					deleteUser(user);
				}
			});
		};
		const openHistory = (user) => {
			historyEntityName.value = user.name;
			historyEndpoint.value = `/admin/users/${user.id}/activity-logs`;
			historyVisible.value = true;
		};
		onMounted(() => {
			fetchUsers();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: unref(t)("users.title") }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				"nav-items": unref(adminNavItems),
				"page-title": unref(t)("users.title")
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<section class="space-y-6"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							columns: columns.value,
							rows: unref(users),
							loading: unref(loading),
							"total-records": unref(totalRecords),
							"current-page": unref(currentPage),
							"rows-per-page": unref(rowsPerPage),
							search: unref(search),
							"sort-field": unref(sortBy),
							"sort-order": unref(tableSortOrder),
							"create-label": unref(t)("users.createUser"),
							"search-label": unref(t)("users.searchUsers"),
							"table-title": unref(t)("users.tableTitle"),
							"show-history": true,
							"onUpdate:search": ($event) => search.value = $event,
							onPageChange: unref(handlePageChange),
							onSortChange: unref(handleSortChange),
							onCreate: openCreate,
							onHistory: openHistory,
							onEdit: openEdit,
							onDelete: askDeleteUser
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(unref(ConfirmPopup), null, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							modelValue: historyVisible.value,
							"onUpdate:modelValue": ($event) => historyVisible.value = $event,
							endpoint: historyEndpoint.value,
							"entity-name": historyEntityName.value
						}, null, _parent, _scopeId));
						_push(`</section>`);
					} else return [createVNode("section", { class: "space-y-6" }, [
						createVNode(_sfc_main$2),
						createVNode(_sfc_main$3, {
							columns: columns.value,
							rows: unref(users),
							loading: unref(loading),
							"total-records": unref(totalRecords),
							"current-page": unref(currentPage),
							"rows-per-page": unref(rowsPerPage),
							search: unref(search),
							"sort-field": unref(sortBy),
							"sort-order": unref(tableSortOrder),
							"create-label": unref(t)("users.createUser"),
							"search-label": unref(t)("users.searchUsers"),
							"table-title": unref(t)("users.tableTitle"),
							"show-history": true,
							"onUpdate:search": ($event) => search.value = $event,
							onPageChange: unref(handlePageChange),
							onSortChange: unref(handleSortChange),
							onCreate: openCreate,
							onHistory: openHistory,
							onEdit: openEdit,
							onDelete: askDeleteUser
						}, null, 8, [
							"columns",
							"rows",
							"loading",
							"total-records",
							"current-page",
							"rows-per-page",
							"search",
							"sort-field",
							"sort-order",
							"create-label",
							"search-label",
							"table-title",
							"onUpdate:search",
							"onPageChange",
							"onSortChange"
						]),
						createVNode(unref(ConfirmPopup)),
						createVNode(_sfc_main$4, {
							modelValue: historyVisible.value,
							"onUpdate:modelValue": ($event) => historyVisible.value = $event,
							endpoint: historyEndpoint.value,
							"entity-name": historyEntityName.value
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"endpoint",
							"entity-name"
						])
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Users.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };
