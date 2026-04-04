import { createI18n } from 'vue-i18n';
import ar from '../locales/ar.json';
import en from '../locales/en.json';

const SUPPORTED_LOCALES = ['en', 'ar'];
let i18nInstance = null;

export const normalizeLocale = (locale) => (SUPPORTED_LOCALES.includes(locale) ? locale : 'en');

export const createAppI18n = (locale = 'en') => {
    i18nInstance = createI18n({
        legacy: false,
        locale: normalizeLocale(locale),
        fallbackLocale: 'en',
        messages: {
            en,
            ar,
        },
    });

    return i18nInstance;
};

export const setI18nLocale = (locale) => {
    if (!i18nInstance) return;
    i18nInstance.global.locale.value = normalizeLocale(locale);
};
