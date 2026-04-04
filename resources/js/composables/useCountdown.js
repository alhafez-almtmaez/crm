import { computed, onMounted, onUnmounted, ref } from 'vue';

export function useCountdown({ durationMs, storageKey }) {
    const fallbackTargetMs = Date.now() + durationMs;
    const targetMs = ref(fallbackTargetMs);
    const remainingMs = ref(Math.max(fallbackTargetMs - Date.now(), 0));

    let timerId;

    const dayMs = 24 * 60 * 60 * 1000;
    const hourMs = 60 * 60 * 1000;
    const minuteMs = 60 * 1000;

    const days = computed(() => Math.floor(remainingMs.value / dayMs));
    const hours = computed(() => Math.floor((remainingMs.value % dayMs) / hourMs));
    const minutes = computed(() => Math.floor((remainingMs.value % hourMs) / minuteMs));
    const seconds = computed(() => Math.floor((remainingMs.value % minuteMs) / 1000));

    const getOrCreateTargetMs = () => {
        if (typeof window === 'undefined') {
            return fallbackTargetMs;
        }

        const storedValue = Number.parseInt(window.localStorage.getItem(storageKey) ?? '', 10);

        if (Number.isFinite(storedValue) && storedValue > 0) {
            return storedValue;
        }

        const freshTargetMs = Date.now() + durationMs;
        window.localStorage.setItem(storageKey, String(freshTargetMs));
        return freshTargetMs;
    };

    onMounted(() => {
        targetMs.value = getOrCreateTargetMs();
        remainingMs.value = Math.max(targetMs.value - Date.now(), 0);

        timerId = setInterval(() => {
            remainingMs.value = Math.max(targetMs.value - Date.now(), 0);

            if (remainingMs.value === 0) {
                clearInterval(timerId);
            }
        }, 1000);
    });

    onUnmounted(() => {
        clearInterval(timerId);
    });

    return {
        targetMs,
        remainingMs,
        days,
        hours,
        minutes,
        seconds,
    };
}
