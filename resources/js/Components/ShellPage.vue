<script setup lang="ts">
import { computed } from 'vue';
import ShellLayout from '../Layouts/ShellLayout.vue';

interface Crumb {
    text: string;
    icon?: string;
    active?: boolean;
}

const props = withDefaults(defineProps<{
    /** Page title, rendered as the (single or last) breadcrumb. */
    title: string;
    /** Icon for the title crumb. */
    icon: string;
    /** Optional crumbs before the title (e.g. [{ text: 'Admin', icon: 'shield-lock' }]). */
    parents?: Crumb[];
    /** Pad the scrollable contents area. */
    padded?: boolean;
}>(), {
    parents: () => [],
    padded: true,
});

const breadcrumbItems = computed(() => [
    ...props.parents.map((c) => ({ ...c, active: false })),
    { text: props.title, icon: props.icon, active: true },
]);
</script>

<template>
    <!-- Full-width shell page: rail + breadcrumb top bar, no folder or
         detail columns. The workhorse for admin/profile/utility surfaces. -->
    <ShellLayout :folders-visible="false" :detail-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 px-3 py-2">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0">
                    <template #item="{ item }">
                        <VibeIcon :icon="item.icon || icon" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <div v-if="$slots.actions" class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <slot name="actions" />
                </div>
                <div v-else-if="$slots.meta" class="d-flex align-items-center gap-2 min-w-0">
                    <slot name="meta" />
                </div>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1" :class="padded ? 'p-3' : ''">
                <slot />
            </div>
        </template>
    </ShellLayout>
</template>
