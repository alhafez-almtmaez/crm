<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
});

const page = usePage();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const contentByStatus = {
    403: {
        key: 'forbidden',
    },
    404: {
        key: 'notFound',
    },
    419: {
        key: 'expired',
    },
    500: {
        key: 'serverError',
    },
    503: {
        key: 'unavailable',
    },
};

const statusContent = computed(() => contentByStatus[props.status] ?? contentByStatus[500]);
const dashboardUrl = '/admin/dashboard';
const homeUrl = '/';
</script>

<template>
    <Head :title="`${status} • ${$t(`errors.${statusContent.key}.title`)}`" />

    <div
        class="flex min-h-screen items-center justify-center bg-(--background) px-6 py-10 text-(--foreground)"
    >
        <section class="w-full max-w-4xl text-center">
            <p
                class="text-8xl font-black leading-none md:text-9xl"
                :style="{ color: 'var(--accent)' }"
            >
                {{ status }}
            </p>
            <h1 class="mt-6 text-3xl font-bold md:text-5xl">
                {{ $t(`errors.${statusContent.key}.title`) }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-(--muted-foreground)">
                {{ $t(`errors.${statusContent.key}.description`) }}
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <Button
                    v-if="isAuthenticated"
                    icon="pi pi-home"
                    :label="$t('errors.actions.backToDashboard')"
                    @click="router.visit(dashboardUrl)"
                />

                <Button
                    v-else
                    icon="pi pi-home"
                    :label="$t('errors.actions.backToHome')"
                    @click="router.visit(homeUrl)"
                />
            </div>
        </section>
    </div>
</template>
