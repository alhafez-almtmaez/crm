import { ref } from 'vue';

const STORAGE_KEY = 'vita_theme_mode';
export const mode = ref('light');

const getSystemMode = () => {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyMode = (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    root.classList.remove('light', 'dark');
    root.classList.add(value);
    mode.value = value;
};

export const initThemeMode = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const saved = window.localStorage.getItem(STORAGE_KEY);
    const initial = saved === 'dark' || saved === 'light' ? saved : getSystemMode();
    applyMode(initial);
};

export const useThemeMode = () => {
    const setMode = (value) => {
        const next = value === 'dark' ? 'dark' : 'light';
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
