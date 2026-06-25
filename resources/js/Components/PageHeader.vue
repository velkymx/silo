<script setup lang="ts">
interface Crumb {
    text: string;
    href?: string;
    active?: boolean;
}

defineProps<{
    title?: string;
    icon?: string;
    breadcrumbs?: Crumb[];
}>();

const emit = defineEmits<{ crumb: [Crumb] }>();
</script>

<template>
    <header class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <VibeBreadcrumb
            v-if="breadcrumbs?.length"
            :items="breadcrumbs"
            class="mb-0 me-2"
            @item-click="emit('crumb', $event.item)"
        />
        <h1 v-else class="h4 mb-0 d-flex align-items-center gap-2">
            <VibeIcon v-if="icon" :icon="icon" class="text-primary" />{{ title }}
        </h1>
        <div v-if="$slots.actions" class="ms-auto d-flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
