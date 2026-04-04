import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { createAppI18n } from './i18n';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} | Vita` : 'Vita'),
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
        setup({ App, props, plugin }) {
            const i18n = createAppI18n(props.initialPage?.props?.systemSettings?.language ?? 'en');

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(i18n)
                .use(PrimeVue, {
                    theme: {
                        preset: Aura,
                        options: {
                            darkModeSelector: '.dark',
                        },
                    },
                });
        },
    }),
);
