import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import { createI18n } from "vue-i18n";
//#region node_modules/laravel-vite-plugin/inertia-helpers/index.js
async function resolvePageComponent(path, pages) {
	for (const p of Array.isArray(path) ? path : [path]) {
		const page = pages[p];
		if (typeof page === "undefined") continue;
		return typeof page === "function" ? page() : page;
	}
	throw new Error(`Page not found: ${path}`);
}
var ar_default = {
	auth: {
		"login": "تسجيل الدخول",
		"signIn": "تسجيل الدخول",
		"useAdminAccount": "استخدم حساب المشرف للوصول إلى لوحة التحكم.",
		"email": "البريد الإلكتروني",
		"password": "كلمة المرور",
		"rememberMe": "تذكرني",
		"appLogoAlt": "شعار {appName}"
	},
	nav: {
		"dashboard": "لوحة التحكم",
		"whatsapp": "واتساب",
		"users": "المستخدمون",
		"roles": "الأدوار",
		"plans": "الخطط",
		"activityLogs": "سجل النشاط",
		"settings": "الإعدادات",
		"management": "الإدارة"
	},
	topbar: {
		"openSidebar": "فتح الشريط الجانبي",
		"expandSidebar": "توسيع الشريط الجانبي",
		"collapseSidebar": "طي الشريط الجانبي",
		"lightMode": "الوضع الفاتح",
		"darkMode": "الوضع الداكن",
		"enterFullscreen": "الدخول إلى ملء الشاشة",
		"exitFullscreen": "الخروج من ملء الشاشة",
		"logout": "تسجيل الخروج",
		"admin": "المشرف",
		"openProfileMenu": "فتح قائمة الملف الشخصي"
	},
	common: {
		"app": "تطبيق",
		"search": "بحث",
		"create": "إنشاء",
		"cancel": "إلغاء",
		"save": "حفظ",
		"saveChanges": "حفظ التغييرات",
		"close": "إغلاق",
		"loading": "جاري التحميل...",
		"id": "المعرف",
		"records": "السجلات",
		"actions": "الإجراءات",
		"noRecords": "لا توجد بيانات.",
		"selectedCount": "{0} محدد",
		"na": "غير متوفر",
		"success": "نجاح",
		"error": "خطأ",
		"info": "معلومة",
		"required": "مطلوب"
	},
	breadcrumbs: {
		"ariaLabel": "مسار التنقل",
		"admin": "الإدارة",
		"dashboard": "لوحة التحكم",
		"whatsapp": "واتساب",
		"users": "المستخدمون",
		"roles": "الأدوار",
		"plans": "الخطط",
		"activityLogs": "سجل النشاط",
		"settings": "الإعدادات",
		"password": "كلمة المرور",
		"create": "إنشاء",
		"edit": "تعديل"
	},
	profile: {
		"updatePassword": "تحديث كلمة المرور",
		"passwordDescription": "غيّر كلمة مرور حسابك بشكل آمن.",
		"currentPassword": "كلمة المرور الحالية",
		"newPassword": "كلمة المرور الجديدة"
	},
	users: {
		"title": "المستخدمون",
		"tableTitle": "جدول المستخدمين",
		"searchUsers": "بحث المستخدمين",
		"createUser": "إنشاء مستخدم",
		"editUser": "تعديل مستخدم",
		"newUser": "مستخدم جديد",
		"addNew": "أضف حساب مستخدم جديد إلى النظام.",
		"editDescription": "حدّث بيانات المستخدم أو عيّن كلمة مرور جديدة.",
		"newUserDescription": "املأ جميع الحقول المطلوبة لإنشاء مستخدم.",
		"name": "الاسم",
		"role": "الدور",
		"confirmPassword": "تأكيد كلمة المرور",
		"newPassword": "كلمة المرور الجديدة",
		"createdAt": "تاريخ الإنشاء",
		"deleteConfirm": "حذف \"{name}\"؟ لا يمكن التراجع عن هذا الإجراء.",
		"deleteUser": "حذف المستخدم"
	},
	roles: {
		"title": "الأدوار",
		"tableTitle": "جدول الأدوار",
		"searchRoles": "بحث الأدوار",
		"createRole": "إنشاء دور",
		"editRole": "تعديل دور",
		"permissions": "الصلاحيات",
		"roleName": "اسم الدور",
		"createdAt": "تاريخ الإنشاء",
		"permissionsCount": "الصلاحيات",
		"createDescription": "عرّف دورًا واربطه بالصلاحيات للتحكم في الوصول.",
		"editDescription": "حدّث اسم الدور وربط الصلاحيات.",
		"saveRole": "حفظ الدور",
		"deleteConfirm": "حذف الدور \"{name}\"؟ لا يمكن التراجع عن هذا الإجراء.",
		"deleteRole": "حذف الدور",
		"adminRoleProtected": "دور المشرف محمي ولا يمكن تعديله أو حذفه."
	},
	plans: {
		"title": "الخطط",
		"tableTitle": "جدول الخطط",
		"searchPlans": "بحث الخطط",
		"createPlan": "إنشاء خطة",
		"editPlan": "تعديل خطة",
		"newPlan": "خطة جديدة",
		"planName": "اسم الخطة",
		"createdAt": "تاريخ الإنشاء",
		"createDescription": "أضف نوع خطة جديد للطلاب.",
		"editDescription": "حدّث تفاصيل الخطة المحددة.",
		"deleteConfirm": "حذف الخطة \"{name}\"؟ لا يمكن التراجع عن هذا الإجراء.",
		"deletePlan": "حذف الخطة"
	},
	activityLogs: {
		"title": "سجل النشاط",
		"tableTitle": "سجل النشاط",
		"searchLogs": "بحث في السجل",
		"historyFor": "سجل: {name}",
		"noHistory": "لا يوجد سجل نشاط لهذا العنصر.",
		"noChanges": "لا توجد تغييرات قبل/بعد لهذا الحدث.",
		"field": "الحقل",
		"before": "قبل",
		"after": "بعد",
		"by": "بواسطة",
		"description": "الوصف",
		"event": "الحدث",
		"subject": "العنصر",
		"causer": "المنفّذ",
		"createdAt": "تاريخ الإنشاء"
	},
	dashboard: {
		"title": "لوحة التحكم",
		"subtitle": "نظرة تشغيلية سريعة لمنطقة الإدارة.",
		"overview": "نظرة عامة",
		"description": "هذه الصفحة محمية بـ auth:web. يمكنك إدارة مظهر النظام من صفحة الإعدادات.",
		"orders": "الطلبات",
		"products": "المنتجات",
		"customers": "العملاء",
		"revenue": "الإيرادات"
	},
	whatsapp: {
		"title": "واتساب",
		"description": "إدارة جلسة واتساب وإرسال رسائل اختبار بسرعة.",
		"deviceFallback": "الجهاز الرئيسي",
		"deleteDevice": "حذف الجهاز",
		"deleteConfirm": "حذف الجهاز \"{name}\"؟ هذا سيعيد تعيين الجلسة الحالية.",
		"deviceDeleted": "تم حذف الجهاز بنجاح.",
		"deleteFailed": "تعذر حذف الجهاز.",
		"apiMissing": "واجهة WhatsApp API غير مهيأة.",
		"apiMissingHelp": "أضف WHATSAPP_API_URL (واختياريًا WHATSAPP_API_KEY) في ملف البيئة.",
		"instructionsTitle": "استخدم واتساب على هذا النظام",
		"step1": "افتح واتساب على هاتفك.",
		"step2": "اضغط القائمة في أندرويد أو الإعدادات في iPhone.",
		"step3": "اختر الأجهزة المرتبطة ثم ربط جهاز.",
		"step4": "وجّه هاتفك إلى هذه الشاشة لمسح رمز QR.",
		"scanHint": "يتم تحديث رمز QR تلقائيًا كل 30 ثانية.",
		"refreshNow": "تحديث الآن",
		"connected": "الجهاز متصل. يمكنك إرسال رسالة الآن.",
		"phone": "الهاتف",
		"message": "الرسالة",
		"phonePlaceholder": "962799988888",
		"messagePlaceholder": "تجربة",
		"send": "إرسال",
		"sendSuccess": "تم إرسال الرسالة بنجاح.",
		"sendFailed": "تعذر إرسال رسالة واتساب.",
		"refreshFailed": "تعذر تحديث حالة QR."
	},
	settings: {
		"title": "الإعدادات",
		"branding": "الهوية البصرية",
		"brandingDescription": "تحكم باسم التطبيق والوصف وملفات الهوية المستخدمة في الشريط الجانبي وصفحة الدخول.",
		"brandName": "اسم العلامة",
		"brandTagline": "الوصف المختصر",
		"lightModeLogo": "شعار الوضع الفاتح",
		"darkModeLogo": "شعار الوضع الداكن",
		"appIcon": "أيقونة التطبيق",
		"noLogoUploaded": "لا يوجد شعار مرفوع",
		"uploadLightLogo": "رفع شعار الوضع الفاتح",
		"uploadDarkLogo": "رفع شعار الوضع الداكن",
		"uploadIcon": "رفع الأيقونة",
		"recommendedLogo": "مقترح: شعار بخلفية شفافة بارتفاع يقارب 36px.",
		"recommendedDarkLogo": "استخدم شعارًا فاتح اللون لخلفيات الوضع الداكن.",
		"recommendedIcon": "مقترح: أيقونة مربعة بحجم 48x48 أو 64x64.",
		"currentLightModeLogo": "الشعار الحالي للوضع الفاتح",
		"currentDarkModeLogo": "الشعار الحالي للوضع الداكن",
		"currentAppIcon": "أيقونة التطبيق الحالية",
		"localization": "الترجمة والاتجاه",
		"localizationDescription": "حدّد اللغة الافتراضية واتجاه الواجهة (LTR/RTL).",
		"defaultLanguage": "اللغة الافتراضية",
		"interfaceDirection": "اتجاه الواجهة",
		"dateTime": "التاريخ والوقت",
		"dateTimeDescription": "حدّد المنطقة الزمنية ومعايير التنسيق العامة.",
		"dateFormat": "تنسيق التاريخ",
		"timeFormat": "تنسيق الوقت",
		"timezone": "المنطقة الزمنية",
		"searchTimezone": "ابحث عن المنطقة الزمنية...",
		"appearance": "المظهر",
		"appearanceDescription": "خصّص الوضع وشكل المكونات ولون التمييز للوضع الحالي.",
		"mode": "الوضع",
		"componentShape": "شكل المكونات",
		"sidebarBehavior": "حجم الشريط الجانبي",
		"sidebarDefault": "افتراضي",
		"sidebarCondensed": "مكثف",
		"sidebarHidden": "مخفي",
		"sidebarSmallHoverActive": "صغير مع التوسعة عند المرور (نشط)",
		"sidebarSmallHover": "صغير مع التوسعة عند المرور",
		"fontFamily": "نوع الخط",
		"accentColor": "لون التمييز ({mode})",
		"light": "فاتح",
		"dark": "داكن",
		"compact": "مضغوط",
		"comfortable": "مريح",
		"rounded": "مستدير",
		"accent": "التمييز",
		"english": "الإنجليزية",
		"arabic": "العربية",
		"ltr": "LTR",
		"rtl": "RTL",
		"uploadAppIcon": "رفع أيقونة التطبيق",
		"uploadDarkModeLogo": "رفع شعار الوضع الداكن",
		"uploadLightModeLogo": "رفع شعار الوضع الفاتح"
	},
	uploads: {
		"uploadImage": "رفع صورة",
		"current": "الحالي",
		"selected": "المحدد",
		"selectedPreview": "معاينة الملف المحدد",
		"noImageSet": "لا توجد صورة",
		"noFileSelected": "لم يتم اختيار ملف",
		"allowedFormats": "المسموح: JPG, PNG, WEBP. الحد الأقصى: 2MB.",
		"upload": "رفع",
		"uploadCompleted": "اكتمل الرفع."
	},
	notifications: {
		"settingsSaved": "تم حفظ التغييرات بنجاح.",
		"settingsSavedTitle": "تم حفظ الإعدادات",
		"saveFailedTitle": "فشل الحفظ",
		"saveFailedDetail": "تعذر حفظ الإعدادات. حاول مرة أخرى.",
		"loadFailedTitle": "فشل التحميل",
		"loadFailedDetail": "تعذر تحميل بيانات الجدول. حاول مرة أخرى.",
		"requestFailedTitle": "فشل الطلب",
		"requestFailedDetail": "حدث خطأ ما. حاول مرة أخرى.",
		"deleteFailedTitle": "فشل الحذف",
		"deleteUserFailed": "تعذر حذف هذا المستخدم.",
		"deleteRoleFailed": "تعذر حذف هذا الدور.",
		"deletePlanFailed": "تعذر حذف هذه الخطة.",
		"userDeleted": "تم حذف المستخدم بنجاح.",
		"roleDeleted": "تم حذف الدور بنجاح.",
		"planDeleted": "تم حذف الخطة بنجاح.",
		"imageUploaded": "تم رفع الصورة بنجاح.",
		"uploadFailedTitle": "فشل الرفع",
		"uploadFailedDetail": "تعذر رفع الصورة. استخدم JPG أو PNG أو WEBP بحجم أقل من 2MB."
	},
	errors: {
		"forbidden": {
			"title": "تم رفض الوصول",
			"description": "لا تملك الصلاحية للوصول إلى هذه الصفحة."
		},
		"notFound": {
			"title": "الصفحة غير موجودة",
			"description": "الصفحة المطلوبة غير موجودة."
		},
		"expired": {
			"title": "انتهت الجلسة",
			"description": "انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى."
		},
		"serverError": {
			"title": "خطأ في الخادم",
			"description": "حدث خطأ من جهة الخادم. حاول مرة أخرى بعد قليل."
		},
		"unavailable": {
			"title": "الخدمة غير متاحة",
			"description": "الخدمة غير متاحة مؤقتًا. حاول مرة أخرى بعد قليل."
		},
		"actions": {
			"backToDashboard": "العودة إلى لوحة التحكم",
			"backToHome": "العودة إلى الصفحة الرئيسية",
			"tryAgain": "إعادة المحاولة"
		}
	}
};
var en_default = {
	auth: {
		"login": "Login",
		"signIn": "Sign in",
		"useAdminAccount": "Use your admin account to access the dashboard.",
		"email": "Email",
		"password": "Password",
		"rememberMe": "Remember me",
		"appLogoAlt": "{appName} logo"
	},
	nav: {
		"dashboard": "Dashboard",
		"whatsapp": "WhatsApp",
		"users": "Users",
		"roles": "Roles",
		"plans": "Plans",
		"activityLogs": "Activity Logs",
		"settings": "Settings",
		"management": "Management"
	},
	topbar: {
		"openSidebar": "Open sidebar",
		"expandSidebar": "Expand sidebar",
		"collapseSidebar": "Collapse sidebar",
		"lightMode": "Light mode",
		"darkMode": "Dark mode",
		"enterFullscreen": "Enter fullscreen",
		"exitFullscreen": "Exit fullscreen",
		"logout": "Logout",
		"admin": "Admin",
		"openProfileMenu": "Open profile menu"
	},
	common: {
		"app": "App",
		"search": "Search",
		"create": "Create",
		"cancel": "Cancel",
		"save": "Save",
		"saveChanges": "Save Changes",
		"close": "Close",
		"loading": "Loading...",
		"id": "ID",
		"records": "Records",
		"actions": "Actions",
		"noRecords": "No records found.",
		"selectedCount": "{0} selected",
		"na": "N/A",
		"success": "Success",
		"error": "Error",
		"info": "Info",
		"required": "required"
	},
	breadcrumbs: {
		"ariaLabel": "Breadcrumb",
		"admin": "Admin",
		"dashboard": "Dashboard",
		"whatsapp": "WhatsApp",
		"users": "Users",
		"roles": "Roles",
		"plans": "Plans",
		"activityLogs": "Activity Logs",
		"settings": "Settings",
		"password": "Password",
		"create": "Create",
		"edit": "Edit"
	},
	profile: {
		"updatePassword": "Update Password",
		"passwordDescription": "Change your account password securely.",
		"currentPassword": "Current Password",
		"newPassword": "New Password"
	},
	users: {
		"title": "Users",
		"tableTitle": "Users Table",
		"searchUsers": "Search Users",
		"createUser": "Create User",
		"editUser": "Edit User",
		"newUser": "New User",
		"addNew": "Add a new user account to the system.",
		"editDescription": "Update user profile details or set a new password.",
		"newUserDescription": "Fill all required fields to create a user.",
		"name": "Name",
		"role": "Role",
		"confirmPassword": "Confirm Password",
		"newPassword": "New Password",
		"createdAt": "Created At",
		"deleteConfirm": "Delete \"{name}\"? This action cannot be undone.",
		"deleteUser": "Delete User"
	},
	roles: {
		"title": "Roles",
		"tableTitle": "Roles Table",
		"searchRoles": "Search Roles",
		"createRole": "Create Role",
		"editRole": "Edit Role",
		"permissions": "Permissions",
		"roleName": "Role Name",
		"createdAt": "Created At",
		"permissionsCount": "Permissions",
		"createDescription": "Define a role and attach permissions for admin access control.",
		"editDescription": "Update role name and permissions mappings.",
		"saveRole": "Save Role",
		"deleteConfirm": "Delete role \"{name}\"? This action cannot be undone.",
		"deleteRole": "Delete Role",
		"adminRoleProtected": "Admin role is protected and cannot be edited or deleted."
	},
	plans: {
		"title": "Plans",
		"tableTitle": "Plans Table",
		"searchPlans": "Search Plans",
		"createPlan": "Create Plan",
		"editPlan": "Edit Plan",
		"newPlan": "New Plan",
		"planName": "Plan Name",
		"createdAt": "Created At",
		"createDescription": "Add a new student plan type.",
		"editDescription": "Update the selected plan details.",
		"deleteConfirm": "Delete plan \"{name}\"? This action cannot be undone.",
		"deletePlan": "Delete Plan"
	},
	activityLogs: {
		"title": "Activity Logs",
		"tableTitle": "Activity Logs",
		"searchLogs": "Search Logs",
		"historyFor": "History: {name}",
		"noHistory": "No activity history found for this item.",
		"noChanges": "No before/after changes captured for this event.",
		"field": "Field",
		"before": "Before",
		"after": "After",
		"by": "By",
		"description": "Description",
		"event": "Event",
		"subject": "Subject",
		"causer": "Causer",
		"createdAt": "Created At"
	},
	dashboard: {
		"title": "Dashboard",
		"subtitle": "Operational snapshot for your admin area.",
		"overview": "Overview",
		"description": "Protected with auth:web. Manage system theme from the Settings page.",
		"orders": "Orders",
		"products": "Products",
		"customers": "Customers",
		"revenue": "Revenue"
	},
	whatsapp: {
		"title": "WhatsApp",
		"description": "Manage your WhatsApp session and send quick test messages.",
		"deviceFallback": "Main Device",
		"deleteDevice": "Delete Device",
		"deleteConfirm": "Delete device \"{name}\"? This will reset the current session.",
		"deviceDeleted": "Device deleted successfully.",
		"deleteFailed": "Could not delete the device.",
		"apiMissing": "WhatsApp API is not configured.",
		"apiMissingHelp": "Set WHATSAPP_API_URL (and optional WHATSAPP_API_KEY) in your environment file.",
		"instructionsTitle": "Use WhatsApp on this system",
		"step1": "Open WhatsApp on your phone.",
		"step2": "Tap Menu on Android, or Settings on iPhone.",
		"step3": "Tap Linked devices, then Link a device.",
		"step4": "Point your phone at this screen to scan the QR code.",
		"scanHint": "QR refreshes automatically every 30 seconds.",
		"refreshNow": "Refresh now",
		"connected": "Device is connected. You can send a message now.",
		"phone": "Phone",
		"message": "Message",
		"phonePlaceholder": "962799988888",
		"messagePlaceholder": "Test",
		"send": "Send",
		"sendSuccess": "Message sent successfully.",
		"sendFailed": "Could not send WhatsApp message.",
		"refreshFailed": "Could not refresh QR state."
	},
	settings: {
		"title": "Settings",
		"branding": "Branding",
		"brandingDescription": "Control app name, tagline, and branding assets used in sidebar and login.",
		"brandName": "Brand Name",
		"brandTagline": "Brand Tagline",
		"lightModeLogo": "Light Mode Logo",
		"darkModeLogo": "Dark Mode Logo",
		"appIcon": "App Icon",
		"noLogoUploaded": "No logo uploaded",
		"uploadLightLogo": "Upload Light Logo",
		"uploadDarkLogo": "Upload Dark Logo",
		"uploadIcon": "Upload Icon",
		"recommendedLogo": "Recommended: transparent logo, around 36px height.",
		"recommendedDarkLogo": "Use a light-colored logo for dark backgrounds.",
		"recommendedIcon": "Recommended: square icon at 48x48px or 64x64px.",
		"currentLightModeLogo": "Current light mode logo",
		"currentDarkModeLogo": "Current dark mode logo",
		"currentAppIcon": "Current app icon",
		"localization": "Localization",
		"localizationDescription": "Set default language and interface direction (LTR/RTL).",
		"defaultLanguage": "Default Language",
		"interfaceDirection": "Interface Direction",
		"dateTime": "Date & Time",
		"dateTimeDescription": "Set global timezone and formatting standards.",
		"dateFormat": "Date Format",
		"timeFormat": "Time Format",
		"timezone": "Timezone",
		"searchTimezone": "Search timezone...",
		"appearance": "Appearance",
		"appearanceDescription": "Customize mode, component shape, and accent color for the active theme.",
		"mode": "Mode",
		"componentShape": "Component Shape",
		"sidebarBehavior": "Sidebar Size",
		"sidebarDefault": "Default",
		"sidebarCondensed": "Condensed",
		"sidebarHidden": "Hidden",
		"sidebarSmallHoverActive": "Small Hover Active",
		"sidebarSmallHover": "Small Hover",
		"fontFamily": "Font Family",
		"accentColor": "Accent Color ({mode})",
		"light": "Light",
		"dark": "Dark",
		"compact": "Compact",
		"comfortable": "Comfortable",
		"rounded": "Rounded",
		"accent": "Accent",
		"english": "English",
		"arabic": "Arabic",
		"ltr": "LTR",
		"rtl": "RTL",
		"uploadAppIcon": "Upload App Icon",
		"uploadDarkModeLogo": "Upload Dark Mode Logo",
		"uploadLightModeLogo": "Upload Light Mode Logo"
	},
	uploads: {
		"uploadImage": "Upload Image",
		"current": "Current",
		"selected": "Selected",
		"selectedPreview": "Selected preview",
		"noImageSet": "No image set",
		"noFileSelected": "No file selected",
		"allowedFormats": "Allowed: JPG, PNG, WEBP. Max size: 2MB.",
		"upload": "Upload",
		"uploadCompleted": "Upload completed."
	},
	notifications: {
		"settingsSaved": "Your changes were saved successfully.",
		"settingsSavedTitle": "Settings Saved",
		"saveFailedTitle": "Save Failed",
		"saveFailedDetail": "Could not save settings. Please try again.",
		"loadFailedTitle": "Load Failed",
		"loadFailedDetail": "Could not load table data. Please try again.",
		"requestFailedTitle": "Request Failed",
		"requestFailedDetail": "Something went wrong. Please try again.",
		"deleteFailedTitle": "Delete Failed",
		"deleteUserFailed": "Could not delete this user.",
		"deleteRoleFailed": "Could not delete this role.",
		"deletePlanFailed": "Could not delete this plan.",
		"userDeleted": "User deleted successfully.",
		"roleDeleted": "Role deleted successfully.",
		"planDeleted": "Plan deleted successfully.",
		"imageUploaded": "Image uploaded successfully.",
		"uploadFailedTitle": "Upload Failed",
		"uploadFailedDetail": "Could not upload image. Please use JPG, PNG, or WEBP under 2MB."
	},
	errors: {
		"forbidden": {
			"title": "Access Denied",
			"description": "You do not have permission to access this page."
		},
		"notFound": {
			"title": "Page Not Found",
			"description": "The page you requested could not be found."
		},
		"expired": {
			"title": "Session Expired",
			"description": "Your session has expired. Please refresh and try again."
		},
		"serverError": {
			"title": "Server Error",
			"description": "Something went wrong on our side. Please try again in a moment."
		},
		"unavailable": {
			"title": "Service Unavailable",
			"description": "The service is temporarily unavailable. Please try again shortly."
		},
		"actions": {
			"backToDashboard": "Back to Dashboard",
			"backToHome": "Back to Home",
			"tryAgain": "Try Again"
		}
	}
};
//#endregion
//#region resources/js/i18n/index.js
var SUPPORTED_LOCALES = ["en", "ar"];
var i18nInstance = null;
var normalizeLocale = (locale) => SUPPORTED_LOCALES.includes(locale) ? locale : "en";
var createAppI18n = (locale = "en") => {
	i18nInstance = createI18n({
		legacy: false,
		locale: normalizeLocale(locale),
		fallbackLocale: "en",
		messages: {
			en: en_default,
			ar: ar_default
		}
	});
	return i18nInstance;
};
var setI18nLocale = (locale) => {
	if (!i18nInstance) return;
	i18nInstance.global.locale.value = normalizeLocale(locale);
};
//#endregion
//#region resources/js/ssr.js
createServer((page) => createInertiaApp({
	page,
	render: renderToString,
	title: (title) => title ? `${title} | Vita` : "Vita",
	resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, /* @__PURE__ */ Object.assign({
		"./Pages/Admin/ActivityLogs.vue": () => import("./assets/ActivityLogs-uXpwczkA.js"),
		"./Pages/Admin/Dashboard.vue": () => import("./assets/Dashboard-BLO6zgRP.js"),
		"./Pages/Admin/Plans/Create.vue": () => import("./assets/Create-C8afBWsE.js"),
		"./Pages/Admin/Plans/Edit.vue": () => import("./assets/Edit-BSZtqmeX.js"),
		"./Pages/Admin/Plans.vue": () => import("./assets/Plans-CGuE4AWg.js"),
		"./Pages/Admin/Profile/Password.vue": () => import("./assets/Password-DVnfDlFI.js"),
		"./Pages/Admin/Roles/Create.vue": () => import("./assets/Create-BFgRq7bf.js"),
		"./Pages/Admin/Roles/Edit.vue": () => import("./assets/Edit-jUYQOPJP.js"),
		"./Pages/Admin/Roles.vue": () => import("./assets/Roles-CydpZ03S.js"),
		"./Pages/Admin/Settings.vue": () => import("./assets/Settings-Q4gomKKE.js"),
		"./Pages/Admin/Users/Create.vue": () => import("./assets/Create-CTPVRbAj.js"),
		"./Pages/Admin/Users/Edit.vue": () => import("./assets/Edit-DihBX8jo.js"),
		"./Pages/Admin/Users.vue": () => import("./assets/Users-DJ8BoykS.js"),
		"./Pages/Admin/WhatsApp.vue": () => import("./assets/WhatsApp-CnduwVSB.js"),
		"./Pages/Auth/Login.vue": () => import("./assets/Login-DB8yyyXw.js"),
		"./Pages/Error.vue": () => import("./assets/Error-D555ua16.js")
	})),
	setup({ App, props, plugin }) {
		const i18n = createAppI18n(props.initialPage?.props?.systemSettings?.language ?? "en");
		return createSSRApp({ render: () => h(App, props) }).use(plugin).use(i18n).use(PrimeVue, { theme: {
			preset: Aura,
			options: { darkModeSelector: ".dark" }
		} });
	}
}));
//#endregion
export { setI18nLocale as t };
