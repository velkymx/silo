<script setup lang="ts">
interface ActionItem { text: string; icon: string; action: string }
interface FileLike { id: number; starred?: boolean }

defineProps<{ item: FileLike; menu?: ActionItem[] }>();
const emit = defineEmits<{ star: [FileLike]; action: [unknown] }>();
</script>

<template>
    <div class="d-flex align-items-center gap-1" @click.stop>
        <VibeButton
            variant="link"
            class="p-0"
            :title="item.starred ? 'Unstar' : 'Star'"
            @click="emit('star', item)"
        >
            <VibeIcon :icon="item.starred ? 'star-fill' : 'star'" :class="item.starred ? 'text-warning' : 'text-muted'" />
        </VibeButton>
        <VibeDropdown size="sm" variant="light" menu-end :items="menu" @item-click="emit('action', $event)">
            <template #button><VibeIcon icon="three-dots-vertical" /></template>
            <template #item="{ item: a }"><VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}</template>
        </VibeDropdown>
    </div>
</template>
