import '../css/app.css';
import 'primeicons/primeicons.css';
import 'intl-tel-input/styles';
import axios from 'axios';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import Ripple from 'primevue/ripple';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Aura from '@primeuix/themes/aura';
import { initThemeMode } from './composables/useThemeMode';
import { initSystemSettings } from './composables/useSystemSettings';
import { createAppI18n } from './i18n';

initThemeMode();

const inertiaRoot = document.getElementById('app');
let appName = document.title || 'Vita';

if (inertiaRoot?.dataset?.page) {
    try {
        const page = JSON.parse(inertiaRoot.dataset.page);
        appName = page?.props?.app?.name || appName;
    } catch {
        // Keep default app name when initial page payload is unavailable.
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        }
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

        const initialLocale = props.initialPage?.props?.systemSettings?.language ?? 'en';
        const i18n = createAppI18n(initialLocale);
        const app = createApp({ render: () => h(App, props) });

        app
            .use(plugin)
            .use(i18n)
            .use(ConfirmationService)
            .use(ToastService)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.dark',
                    },
                },
            });

        app.directive('ripple', Ripple);

        app
            .mount(el);

        // PrimeVue runtime token updates must run after the PrimeVue plugin is initialized.
        initSystemSettings(props.initialPage?.props?.systemSettings ?? null);
    },
    progress: {
        color: '#22d3ee',
    },
});
