<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';

defineProps<{
    title: string;
    icon: string;
}>();
</script>

<template>
    <AppLayout>
        <div class="container py-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h4 mb-0 d-flex align-items-center gap-2">
                        <VibeIcon :icon="icon" class="text-primary" />
                        {{ title }}
                    </h1>
                    <p class="text-muted small mb-0"><slot name="subtitle" /></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-auto">
                    <div class="break-shell rounded border p-3 bg-body-tertiary">
                        <slot />
                    </div>

                    <div class="mt-3 text-center break-message">
                        <slot name="message" />
                    </div>

                    <div v-if="$slots.extra" class="mt-4">
                        <slot name="extra" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.break-shell {
    --break-tile-size: clamp(2.5rem, 12vw, 4.375rem);
    --break-tile-gap: 0.375rem;
    width: fit-content;
    margin: 0 auto;
}

.break-message {
    min-height: 1.5em;
}
</style>
