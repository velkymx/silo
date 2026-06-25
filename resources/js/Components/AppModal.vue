<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';

const open = defineModel<boolean>({ required: true });

const props = withDefaults(defineProps<{
    title?: string;
    size?: 'sm' | 'lg' | 'xl';
    fullscreen?: boolean;
    centered?: boolean;
    hideFooter?: boolean;
    scrollable?: boolean;
}>(), {});

const root = ref<InstanceType<typeof HTMLElement> | null>(null);

watch(open, async (v) => {
    if (!v) return;
    await nextTick();
    const el = (root.value as HTMLElement | null)?.querySelector<HTMLElement>(
        'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]):not(.btn-close)',
    );
    el?.focus();
});
</script>

<template>
    <VibeModal
        ref="root"
        v-model="open"
        :title="props.title"
        :size="props.size"
        :fullscreen="props.fullscreen"
        :centered="props.centered"
        :hide-footer="props.hideFooter"
        :scrollable="props.scrollable"
    >
        <slot />
        <template v-if="$slots.header" #header><slot name="header" /></template>
        <template v-if="$slots.footer" #footer><slot name="footer" /></template>
    </VibeModal>
</template>
