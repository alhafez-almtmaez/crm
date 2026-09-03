<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useThemeMode } from '../../composables/useThemeMode';

const props = defineProps({
    portal: {
        type: Object,
        required: true,
    },
});

const { mode, toggleMode } = useThemeMode();
const searchTerm = ref('');
const activeCategory = ref('all');
const copied = ref(false);
let copyResetTimer = null;

const normalizeSearchText = (value) => String(value ?? '')
    .normalize('NFKD')
    .replace(/[\u064B-\u065F\u0670\u0640]/g, '')
    .replace(/[\u0622\u0623\u0625]/g, '\u0627')
    .replace(/\u0649/g, '\u064A')
    .replace(/\u0624/g, '\u0648')
    .replace(/\u0626/g, '\u064A')
    .replace(/\u0629/g, '\u0647')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();

const studentName = computed(() => String(props.portal.student_name ?? '').trim() || '\u0627\u0644\u0637\u0627\u0644\u0628');
const centerName = computed(() => String(props.portal.center_name ?? '').trim());
const brandName = computed(() => String(props.portal.brand_name ?? '').trim() || '\u0645\u0634\u0631\u0648\u0639 \u0627\u0644\u062d\u0627\u0641\u0638 \u0627\u0644\u0645\u062a\u0645\u064a\u0632');
const brandTagline = computed(() => String(props.portal.brand_tagline ?? '').trim());
const logoUrl = computed(() => String(props.portal.logo_url ?? '').trim() || '/media/logos/logo.png');
const portalUrl = computed(() => String(props.portal.portal_url ?? '').trim());
const pageTitle = computed(() => `\u0634\u0647\u0627\u062f\u0627\u062a ${studentName.value}`);
const studentInitial = computed(() => Array.from(studentName.value)[0] ?? '\u0634');

const certificates = computed(() => (Array.isArray(props.portal.certificates) ? props.portal.certificates : [])
    .map((certificate, index) => ({
        ...certificate,
        _originalIndex: index,
        _position: Number.isFinite(Number(certificate?.position)) ? Number(certificate.position) : index + 1,
    }))
    .sort((first, second) => (
        first._position - second._position
        || first._originalIndex - second._originalIndex
    )));

const totalCertificateCount = computed(() => {
    const suppliedCount = Number(props.portal.certificate_count);

    return Number.isFinite(suppliedCount) && suppliedCount >= 0
        ? suppliedCount
        : certificates.value.length;
});

const categoryFilters = computed(() => [
    {
        value: 'all',
        label: '\u0627\u0644\u0643\u0644',
        icon: 'pi pi-th-large',
        count: certificates.value.length,
    },
    {
        value: 'quran',
        label: '\u0627\u0644\u0642\u0631\u0622\u0646',
        icon: 'pi pi-book',
        count: certificates.value.filter((certificate) => certificate.category === 'quran').length,
    },
    {
        value: 'sunnah',
        label: '\u0627\u0644\u0633\u064f\u0651\u0646\u0629',
        icon: 'pi pi-bookmark',
        count: certificates.value.filter((certificate) => certificate.category === 'sunnah').length,
    },
]);
const hasBothCertificateCategories = computed(() => (
    certificates.value.some((certificate) => certificate.category === 'quran')
    && certificates.value.some((certificate) => certificate.category === 'sunnah')
));

const normalizedSearchTerm = computed(() => normalizeSearchText(searchTerm.value));
const filteredCertificates = computed(() => certificates.value.filter((certificate) => {
    if (activeCategory.value !== 'all' && certificate.category !== activeCategory.value) {
        return false;
    }

    if (!normalizedSearchTerm.value) {
        return true;
    }

    const searchableText = normalizeSearchText([
        certificate.type_label,
        certificate.achievement_name,
        certificate.plan_name,
        certificate.plan_point_name,
        certificate.gregorian_date,
    ].filter(Boolean).join(' '));

    return searchableText.includes(normalizedSearchTerm.value);
}));

const formatNumber = (value) => new Intl.NumberFormat('ar-JO').format(Number(value) || 0);
const certificatePosition = (certificate, index) => formatNumber(certificate._position || index + 1);
const emptyMessage = computed(() => {
    if (certificates.value.length === 0) {
        return '\u0644\u0645 \u062a\u0635\u062f\u0631 \u0634\u0647\u0627\u062f\u0627\u062a \u0644\u0647\u0630\u0627 \u0627\u0644\u0637\u0627\u0644\u0628 \u062d\u062a\u0649 \u0627\u0644\u0622\u0646.';
    }

    return '\u0644\u0627 \u062a\u0648\u062c\u062f \u0634\u0647\u0627\u062f\u0627\u062a \u062a\u0637\u0627\u0628\u0642 \u0627\u0644\u0628\u062d\u062b \u0623\u0648 \u0627\u0644\u062a\u0635\u0646\u064a\u0641 \u0627\u0644\u0645\u062d\u062f\u062f.';
});

const copyPortalLink = async () => {
    const url = portalUrl.value || (typeof window !== 'undefined' ? window.location.href : '');

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const input = document.createElement('input');
        input.value = url;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
    }

    copied.value = true;
    window.clearTimeout(copyResetTimer);
    copyResetTimer = window.setTimeout(() => {
        copied.value = false;
    }, 1800);
};

const resetFilters = () => {
    searchTerm.value = '';
    activeCategory.value = 'all';
};

onBeforeUnmount(() => {
    if (copyResetTimer !== null) {
        window.clearTimeout(copyResetTimer);
    }
});
</script>

<template>
    <Head :title="pageTitle">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="referrer" content="no-referrer">
        <link v-if="portalUrl" rel="canonical" :href="portalUrl">
    </Head>

    <main dir="rtl" class="certificate-portal min-h-screen">
        <div class="mx-auto w-full max-w-7xl px-4 py-4 sm:px-6 sm:py-7 lg:px-8">
            <header class="portal-hero relative overflow-hidden rounded-(--radius-lg) border border-(--border) bg-(--card) shadow-(--shadow-sm)">
                <span class="hero-orb hero-orb--start" aria-hidden="true"></span>
                <span class="hero-orb hero-orb--end" aria-hidden="true"></span>

                <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 border-b border-(--border) px-4 py-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl border border-(--border) bg-(--background) p-2 shadow-(--shadow-sm)">
                            <img :src="logoUrl" :alt="`\u0634\u0639\u0627\u0631 ${brandName}`" class="size-full object-contain">
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-[var(--accent)]">{{ brandName }}</p>
                            <p v-if="brandTagline" class="mt-0.5 truncate text-xs text-(--muted-foreground)">{{ brandTagline }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="portal-icon-button"
                            :title="mode === 'dark' ? '\u0627\u0644\u062a\u0628\u062f\u064a\u0644 \u0625\u0644\u0649 \u0627\u0644\u0648\u0636\u0639 \u0627\u0644\u0641\u0627\u062a\u062d' : '\u0627\u0644\u062a\u0628\u062f\u064a\u0644 \u0625\u0644\u0649 \u0627\u0644\u0648\u0636\u0639 \u0627\u0644\u062f\u0627\u0643\u0646'"
                            :aria-label="mode === 'dark' ? '\u0627\u0644\u062a\u0628\u062f\u064a\u0644 \u0625\u0644\u0649 \u0627\u0644\u0648\u0636\u0639 \u0627\u0644\u0641\u0627\u062a\u062d' : '\u0627\u0644\u062a\u0628\u062f\u064a\u0644 \u0625\u0644\u0649 \u0627\u0644\u0648\u0636\u0639 \u0627\u0644\u062f\u0627\u0643\u0646'"
                            @click="toggleMode"
                        >
                            <i :class="mode === 'dark' ? 'pi pi-sun' : 'pi pi-moon'" aria-hidden="true"></i>
                        </button>
                        <button
                            type="button"
                            class="portal-copy-button"
                            :class="{ 'portal-copy-button--copied': copied }"
                            :aria-label="copied ? '\u062a\u0645 \u0646\u0633\u062e \u0631\u0627\u0628\u0637 \u0627\u0644\u0635\u0641\u062d\u0629' : '\u0646\u0633\u062e \u0631\u0627\u0628\u0637 \u0627\u0644\u0635\u0641\u062d\u0629'"
                            @click="copyPortalLink"
                        >
                            <i :class="copied ? 'pi pi-check' : 'pi pi-link'" aria-hidden="true"></i>
                            <span class="hidden sm:inline">{{ copied ? '\u062a\u0645 \u0646\u0633\u062e \u0627\u0644\u0631\u0627\u0628\u0637' : '\u0646\u0633\u062e \u0627\u0644\u0631\u0627\u0628\u0637' }}</span>
                        </button>
                        <span class="sr-only" aria-live="polite">{{ copied ? '\u062a\u0645 \u0646\u0633\u062e \u0631\u0627\u0628\u0637 \u0627\u0644\u0635\u0641\u062d\u0629' : '' }}</span>
                    </div>
                </div>

                <div class="relative z-10 grid gap-6 px-5 py-7 sm:px-8 sm:py-9 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                        <span class="student-avatar grid size-16 shrink-0 place-items-center rounded-2xl text-2xl font-black sm:size-20 sm:text-3xl" aria-hidden="true">
                            {{ studentInitial }}
                        </span>
                        <div class="min-w-0">
                            <p class="mb-1.5 text-sm font-bold text-[var(--accent)]">مكتبة الإنجازات</p>
                            <h1 class="text-2xl font-black leading-tight text-(--foreground) sm:text-4xl">شهادات {{ studentName }}</h1>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-(--muted-foreground)">
                                <span v-if="centerName" class="hero-detail-pill">
                                    <i class="pi pi-building" aria-hidden="true"></i>
                                    {{ centerName }}
                                </span>
                                <span class="hero-detail-pill">
                                    <i class="pi pi-verified" aria-hidden="true"></i>
                                    {{ formatNumber(totalCertificateCount) }} شهادة
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="achievement-counter" aria-label="عدد الشهادات">
                        <span>إنجازات موثقة</span>
                        <strong>{{ formatNumber(totalCertificateCount) }}</strong>
                    </div>
                </div>
            </header>

            <section class="mt-5 rounded-(--radius-lg) border border-(--border) bg-(--card) p-4 text-(--card-foreground) shadow-(--shadow-sm) sm:p-6" aria-labelledby="certificates-gallery-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-[var(--accent)]">سجل الإنجاز</p>
                        <h2 id="certificates-gallery-title" class="mt-1 text-xl font-black sm:text-2xl">الشهادات الصادرة</h2>
                    </div>
                    <span class="results-count">
                        {{ formatNumber(filteredCertificates.length) }} من {{ formatNumber(certificates.length) }}
                    </span>
                </div>

                <div v-if="certificates.length" class="mt-5 grid gap-3 lg:grid-cols-[minmax(18rem,1fr)_auto] lg:items-end">
                    <div>
                        <label for="student-certificate-search" class="mb-2 block text-sm font-bold">البحث في الشهادات</label>
                        <div class="relative">
                            <i class="pi pi-search pointer-events-none absolute inset-y-0 start-4 my-auto h-fit text-(--muted-foreground)" aria-hidden="true"></i>
                            <input
                                id="student-certificate-search"
                                v-model="searchTerm"
                                type="search"
                                autocomplete="off"
                                class="portal-search h-12 w-full rounded-(--radius-md) border border-(--border) bg-(--background) pe-11 ps-11 text-sm text-(--foreground) outline-none transition"
                                placeholder="ابحث باسم السورة، الجزء أو الكتاب..."
                            >
                            <button
                                v-if="searchTerm"
                                type="button"
                                class="search-clear absolute inset-y-0 end-2 my-auto grid size-8 place-items-center rounded-full text-(--muted-foreground)"
                                title="مسح البحث"
                                aria-label="مسح البحث"
                                @click="searchTerm = ''"
                            >
                                <i class="pi pi-times text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="hasBothCertificateCategories">
                        <p class="mb-2 text-sm font-bold">التصنيف</p>
                        <div class="flex flex-wrap gap-2" role="group" aria-label="تصنيف الشهادات">
                            <button
                                v-for="filter in categoryFilters"
                                :key="filter.value"
                                type="button"
                                class="category-filter"
                                :class="{ 'category-filter--active': activeCategory === filter.value }"
                                :aria-pressed="activeCategory === filter.value"
                                @click="activeCategory = filter.value"
                            >
                                <i :class="filter.icon" aria-hidden="true"></i>
                                <span>{{ filter.label }}</span>
                                <span class="category-filter__count">{{ formatNumber(filter.count) }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="filteredCertificates.length" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="(certificate, index) in filteredCertificates"
                        :key="`${certificate.preview_url}-${certificate._originalIndex}`"
                        class="certificate-card group relative flex min-h-72 flex-col overflow-hidden rounded-(--radius-base) border border-(--border) bg-(--background) p-5 transition"
                        :class="certificate.category === 'sunnah' ? 'certificate-card--sunnah' : 'certificate-card--quran'"
                    >
                        <span class="certificate-card__accent" aria-hidden="true"></span>

                        <div class="flex items-start justify-between gap-3">
                            <span class="certificate-type-badge">
                                <i :class="certificate.category === 'sunnah' ? 'pi pi-bookmark' : 'pi pi-book'" aria-hidden="true"></i>
                                {{ certificate.type_label || '\u0634\u0647\u0627\u062f\u0629 \u0625\u0646\u062c\u0627\u0632' }}
                            </span>
                            <span class="certificate-position" :aria-label="`\u0627\u0644\u0634\u0647\u0627\u062f\u0629 \u0631\u0642\u0645 ${certificatePosition(certificate, index)}`">
                                {{ certificatePosition(certificate, index) }}
                            </span>
                        </div>

                        <div class="mt-5 flex-1">
                            <p class="text-xs font-bold text-(--muted-foreground)">الإنجاز</p>
                            <h3 class="mt-1.5 text-xl font-black leading-8 text-(--foreground)">
                                {{ certificate.achievement_name || '\u0625\u0646\u062c\u0627\u0632 \u0645\u062a\u0645\u064a\u0632' }}
                            </h3>

                            <dl class="mt-4 space-y-2.5 text-sm">
                                <div v-if="certificate.plan_name" class="certificate-meta-row">
                                    <dt><i class="pi pi-map" aria-hidden="true"></i><span>الخطة</span></dt>
                                    <dd>{{ certificate.plan_name }}</dd>
                                </div>
                                <div v-if="certificate.plan_point_name" class="certificate-meta-row">
                                    <dt><i class="pi pi-flag" aria-hidden="true"></i><span>المرحلة</span></dt>
                                    <dd>{{ certificate.plan_point_name }}</dd>
                                </div>
                                <div v-if="certificate.gregorian_date" class="certificate-meta-row">
                                    <dt><i class="pi pi-calendar" aria-hidden="true"></i><span>تاريخ الإنجاز</span></dt>
                                    <dd dir="ltr">{{ certificate.gregorian_date }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-2.5 border-t border-(--border) pt-4">
                            <a
                                :href="certificate.preview_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="certificate-action certificate-action--primary"
                            >
                                <i class="pi pi-eye" aria-hidden="true"></i>
                                <span>مشاهدة</span>
                            </a>
                            <a
                                :href="certificate.pdf_url"
                                class="certificate-action certificate-action--secondary"
                            >
                                <i class="pi pi-file-pdf" aria-hidden="true"></i>
                                <span>تنزيل PDF</span>
                            </a>
                        </div>
                    </article>
                </div>

                <div v-else class="empty-state mt-6 rounded-(--radius-base) border border-dashed border-(--border) px-5 py-12 text-center">
                    <span class="mx-auto grid size-16 place-items-center rounded-2xl" aria-hidden="true">
                        <i :class="certificates.length ? 'pi pi-search' : 'pi pi-id-card'" class="text-2xl"></i>
                    </span>
                    <h3 class="mt-4 text-lg font-black">{{ certificates.length ? '\u0644\u0627 \u062a\u0648\u062c\u062f \u0646\u062a\u0627\u0626\u062c' : '\u0644\u0627 \u062a\u0648\u062c\u062f \u0634\u0647\u0627\u062f\u0627\u062a \u062d\u0627\u0644\u064a\u064b\u0627' }}</h3>
                    <p class="mx-auto mt-2 max-w-lg text-sm leading-7 text-(--muted-foreground)">{{ emptyMessage }}</p>
                    <button
                        v-if="certificates.length"
                        type="button"
                        class="empty-reset-button mt-5"
                        @click="resetFilters"
                    >
                        <i class="pi pi-refresh" aria-hidden="true"></i>
                        عرض كل الشهادات
                    </button>
                </div>
            </section>

            <footer class="px-3 py-6 text-center text-xs leading-6 text-(--muted-foreground)">
                <p>جميع الشهادات المعروضة صادرة عن {{ brandName }}.</p>
            </footer>
        </div>
    </main>
</template>

<style scoped>
.certificate-portal {
    background:
        radial-gradient(circle at 8% 4%, color-mix(in oklab, var(--accent) 12%, transparent), transparent 28rem),
        radial-gradient(circle at 92% 96%, color-mix(in oklab, var(--status-info) 10%, transparent), transparent 32rem),
        var(--background);
    color: var(--foreground);
    font-family: Cairo, Tajawal, var(--app-font-sans);
}

.portal-hero {
    isolation: isolate;
}

.hero-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(2px);
    opacity: 0.85;
    pointer-events: none;
}

.hero-orb--start {
    inset-block-end: -7rem;
    inset-inline-start: -4rem;
    width: 19rem;
    height: 19rem;
    background: color-mix(in oklab, var(--accent) 13%, transparent);
}

.hero-orb--end {
    inset-block-start: 3.25rem;
    inset-inline-end: -5rem;
    width: 16rem;
    height: 16rem;
    background: color-mix(in oklab, var(--status-info) 9%, transparent);
}

.portal-icon-button,
.portal-copy-button,
.category-filter,
.certificate-action,
.empty-reset-button {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-weight: 700;
    text-decoration: none;
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease, transform 160ms ease;
}

.portal-icon-button {
    width: 2.75rem;
    border: 1px solid var(--border);
    background: var(--background);
    color: var(--foreground);
}

.portal-copy-button {
    border: 1px solid color-mix(in oklab, var(--accent) 34%, var(--border));
    background: color-mix(in oklab, var(--accent) 9%, var(--card));
    color: var(--accent);
    padding-inline: 0.85rem;
}

.portal-copy-button--copied {
    border-color: color-mix(in oklab, var(--status-success) 45%, var(--border));
    background: color-mix(in oklab, var(--status-success) 12%, var(--card));
    color: var(--status-success);
}

.portal-icon-button:hover,
.portal-copy-button:hover {
    background: color-mix(in oklab, var(--accent) 12%, var(--card));
}

.student-avatar {
    border: 1px solid color-mix(in oklab, var(--accent) 35%, var(--border));
    background: color-mix(in oklab, var(--accent) 12%, var(--card));
    color: var(--accent);
    box-shadow: inset 0 0 0 0.35rem color-mix(in oklab, var(--accent) 4%, transparent);
}

.hero-detail-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid var(--border);
    border-radius: 9999px;
    background: color-mix(in oklab, var(--background) 88%, transparent);
    padding: 0.4rem 0.7rem;
}

.achievement-counter {
    display: grid;
    min-width: 9.5rem;
    gap: 0.2rem;
    border: 1px solid color-mix(in oklab, var(--accent) 25%, var(--border));
    border-radius: var(--radius-base);
    background: color-mix(in oklab, var(--accent) 8%, var(--card));
    padding: 1rem 1.15rem;
}

.achievement-counter span {
    color: var(--muted-foreground);
    font-size: 0.75rem;
    font-weight: 700;
}

.achievement-counter strong {
    color: var(--accent);
    font-size: 2rem;
    line-height: 1.1;
}

.results-count {
    border: 1px solid var(--border);
    border-radius: 9999px;
    background: var(--background);
    color: var(--muted-foreground);
    padding: 0.4rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
}

.portal-search::placeholder {
    color: var(--muted-foreground);
    opacity: 0.8;
}

.portal-search:focus {
    border-color: var(--accent);
    box-shadow: var(--focus-ring);
}

.search-clear:hover {
    background: color-mix(in oklab, var(--foreground) 7%, transparent);
    color: var(--foreground);
}

.category-filter {
    border: 1px solid var(--border);
    background: var(--background);
    color: var(--muted-foreground);
    padding-inline: 0.8rem;
}

.category-filter:hover {
    border-color: color-mix(in oklab, var(--accent) 35%, var(--border));
    color: var(--foreground);
}

.category-filter--active {
    border-color: var(--accent);
    background: var(--accent);
    color: var(--accent-contrast);
}

.category-filter__count {
    display: grid;
    min-width: 1.4rem;
    height: 1.4rem;
    place-items: center;
    border-radius: 9999px;
    background: color-mix(in oklab, currentColor 12%, transparent);
    padding-inline: 0.3rem;
    font-size: 0.68rem;
}

.category-filter--active .category-filter__count {
    background: color-mix(in oklab, var(--accent-contrast) 20%, transparent);
}

.certificate-card {
    --certificate-tone: var(--accent);
    box-shadow: 0 10px 28px color-mix(in oklab, var(--foreground) 7%, transparent);
}

.certificate-card--sunnah {
    --certificate-tone: var(--status-info);
}

.certificate-card:hover {
    border-color: color-mix(in oklab, var(--certificate-tone) 38%, var(--border));
    box-shadow: 0 16px 36px color-mix(in oklab, var(--foreground) 10%, transparent);
    transform: translateY(-2px);
}

.certificate-card__accent {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: 0.25rem;
    background: var(--certificate-tone);
}

.certificate-type-badge {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid color-mix(in oklab, var(--certificate-tone) 30%, var(--border));
    border-radius: 9999px;
    background: color-mix(in oklab, var(--certificate-tone) 9%, var(--background));
    color: var(--certificate-tone);
    padding: 0.35rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 800;
}

.certificate-position {
    display: grid;
    width: 2rem;
    height: 2rem;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 0.65rem;
    background: color-mix(in oklab, var(--certificate-tone) 10%, var(--card));
    color: var(--certificate-tone);
    font-size: 0.75rem;
    font-weight: 900;
}

.certificate-meta-row {
    display: grid;
    grid-template-columns: minmax(6rem, auto) minmax(0, 1fr);
    align-items: start;
    gap: 0.75rem;
}

.certificate-meta-row dt {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--muted-foreground);
    font-size: 0.75rem;
    font-weight: 700;
}

.certificate-meta-row dt i {
    color: var(--certificate-tone);
}

.certificate-meta-row dd {
    min-width: 0;
    margin: 0;
    overflow-wrap: anywhere;
    color: var(--foreground);
    font-weight: 700;
    text-align: end;
}

.certificate-action {
    padding-inline: 0.65rem;
}

.certificate-action--primary {
    border: 1px solid var(--certificate-tone);
    background: var(--certificate-tone);
    color: var(--status-info-contrast);
}

.certificate-card--quran .certificate-action--primary {
    color: var(--accent-contrast);
}

.certificate-action--secondary {
    border: 1px solid color-mix(in oklab, var(--certificate-tone) 35%, var(--border));
    background: transparent;
    color: var(--certificate-tone);
}

.certificate-action:hover {
    transform: translateY(-1px);
}

.certificate-action--secondary:hover {
    background: color-mix(in oklab, var(--certificate-tone) 9%, var(--background));
}

.empty-state {
    background: color-mix(in oklab, var(--accent) 3%, var(--background));
}

.empty-state > span {
    background: color-mix(in oklab, var(--accent) 10%, var(--card));
    color: var(--accent);
}

.empty-reset-button {
    border: 1px solid color-mix(in oklab, var(--accent) 35%, var(--border));
    background: color-mix(in oklab, var(--accent) 9%, var(--card));
    color: var(--accent);
    padding-inline: 1rem;
}

.empty-reset-button:hover {
    background: color-mix(in oklab, var(--accent) 15%, var(--card));
}

.portal-icon-button:focus-visible,
.portal-copy-button:focus-visible,
.category-filter:focus-visible,
.certificate-action:focus-visible,
.empty-reset-button:focus-visible,
.search-clear:focus-visible {
    outline: none;
    box-shadow: var(--focus-ring);
}

@media (max-width: 639px) {
    .achievement-counter {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        min-width: 0;
    }

    .achievement-counter strong {
        font-size: 1.6rem;
    }

    .category-filter {
        flex: 1 1 auto;
    }

    .certificate-meta-row {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }

    .certificate-meta-row dd {
        text-align: start;
    }
}

@media (prefers-reduced-motion: reduce) {
    .portal-icon-button,
    .portal-copy-button,
    .category-filter,
    .certificate-card,
    .certificate-action,
    .empty-reset-button {
        transition: none;
    }

    .certificate-card:hover,
    .certificate-action:hover {
        transform: none;
    }
}
</style>
