<script setup lang="ts">
import { computed } from 'vue';
import ShellLayout from '../Layouts/ShellLayout.vue';

const props = defineProps<{
    title: string;
    icon: string;
}>();

const breadcrumbItems = computed(() => [
    { text: 'Break Room', active: false },
    { text: props.title, active: true },
]);
</script>

<template>
    <ShellLayout :folders-visible="false" :detail-visible="false">
        <!-- Breadcrumb + per-game actions share the top-bar line. -->
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-1">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? 'joystick' : icon" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <slot name="actions" />
                </div>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1 py-4 px-3">
                <p v-if="$slots.subtitle" class="text-muted small text-center mb-3"><slot name="subtitle" /></p>

                <div class="row justify-content-center g-0">
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
        </template>
    </ShellLayout>
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
