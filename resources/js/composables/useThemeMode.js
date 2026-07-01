import { shallowRef } from 'vue';

const STORAGE_KEY = 'vita_theme_mode';
const DEFAULT_MODE = 'light';

export const mode = shallowRef(DEFAULT_MODE);

const normalizeMode = (value) => (value === 'dark' ? 'dark' : DEFAULT_MODE);

const applyMode = (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    const next = normalizeMode(value);
    const root = document.documentElement;
    root.classList.remove('light', 'dark');
    root.classList.add(next);
    mode.value = next;
};

export const initThemeMode = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const saved = window.localStorage.getItem(STORAGE_KEY);
    applyMode(saved);
};

export const useThemeMode = () => {
    const setMode = (value) => {
        const next = normalizeMode(value);
        applyMode(next);

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, next);
        }
    };

    const toggleMode = () => {
        setMode(mode.value === 'dark' ? 'light' : 'dark');
    };

    return {
        mode,
        setMode,
        toggleMode,
    };
};
