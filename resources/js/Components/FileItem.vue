<script setup lang="ts">
import { iconFor, colorFor } from '../lib/fileTypes';
import ItemActions from './ItemActions.vue';

interface Tag { id: number; name: string; color?: string }
interface FileItemData {
    id: number;
    name: string;
    type?: string;
    is_dir?: boolean;
    starred?: boolean;
    thumb_url?: string | null;
    size?: number;
    item_count?: number;
    status?: string;
    version?: number;
    tags?: Tag[];
}
interface ActionItem { text: string; icon: string; action: string }

defineProps<{
    item: FileItemData;
    view: 'grid' | 'list';
    selectMode?: boolean;
    selected?: boolean;
    menu?: ActionItem[];
}>();

const emit = defineEmits<{
    open: [FileItemData, MouseEvent];
    'toggle-select': [number];
    star: [FileItemData];
    action: [unknown];
    drop: [unknown, FileItemData];
    tag: [number];
}>();
</script>

<template>
    <VibeDraggable :payload="item" group="files" tag="div" :class="view === 'grid' ? 'h-100' : ''">
        <template #default="{ isDragging }">
            <component
                :is="item.is_dir ? 'VibeDroppable' : 'div'"
                group="files"
                :class="view === 'grid' ? 'h-100' : ''"
                @drop="emit('drop', $event, item)"
            >
                <template #default="drop">
                    <!-- GRID: card -->
                    <div
                        v-if="view === 'grid'"
                        class="card h-100 text-center border position-relative"
                        :class="{ 'opacity-50': isDragging, 'border-primary border-2 shadow': drop && drop.isOver, 'border-primary border-2': selected }"
                        style="cursor: pointer"
                        @click="emit('open', item, $event)"
                    >
                        <VibeIcon
                            v-if="selectMode || selected"
                            :icon="selected ? 'check-circle-fill' : 'circle'"
                            class="position-absolute m-1"
                            :class="selected ? 'text-primary' : 'text-muted'"
                            style="top: 50%; left: 6px; z-index: 2"
                            @click.stop="emit('toggle-select', item.id)"
                        />
                        <div class="position-absolute top-0 end-0 m-1" style="z-index: 2">
                            <ItemActions :item="item" :menu="menu" @star="emit('star', item)" @action="emit('action', $event)" />
                        </div>
                        <div class="d-flex align-items-center justify-content-center bg-body-tertiary rounded-top" style="height: 120px">
                            <img v-if="item.thumb_url" :src="item.thumb_url" :alt="item.name" class="w-100 h-100" style="object-fit: cover">
                            <VibeIcon v-else :icon="item.is_dir ? 'folder-fill' : iconFor(item.type)" class="display-4" :style="{ color: colorFor(item) }" />
                        </div>
                        <div class="p-2">
                            <div class="text-truncate small fw-medium" :title="item.name">{{ item.name }}</div>
                            <div class="text-muted" style="font-size: 0.7rem">
                                {{ item.is_dir ? `${item.item_count} items` : `${((item.size ?? 0) / 1024).toFixed(1)} KB` }}
                            </div>
                            <VibeBadge v-if="item.status === 'pending'" variant="info" class="mt-1">Processing</VibeBadge>
                            <VibeBadge v-else-if="item.status === 'infected'" variant="danger" class="mt-1"><VibeIcon icon="shield-exclamation" class="me-1" />Infected</VibeBadge>
                            <VibeBadge v-else-if="item.status === 'failed'" variant="danger" class="mt-1">Failed</VibeBadge>
                        </div>
                    </div>

                    <!-- LIST: name cell -->
                    <template v-else>
                        <div
                            class="d-flex align-items-center rounded"
                            :class="{ 'opacity-50': isDragging, 'bg-primary-subtle': (drop && drop.isOver) || selected }"
                            style="cursor: pointer"
                            @click="emit('open', item, $event)"
                        >
                            <VibeIcon
                                :icon="selected ? 'check-square-fill' : 'square'"
                                class="me-2 flex-shrink-0 select-check"
                                :class="selected ? 'text-primary' : 'text-muted'"
                                @click.stop="emit('toggle-select', item.id)"
                            />
                            <img v-if="item.thumb_url" :src="item.thumb_url" :alt="item.name" class="rounded border me-2 flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover">
                            <VibeIcon v-else :icon="item.is_dir ? 'folder-fill' : iconFor(item.type)" class="me-2 fs-4 flex-shrink-0" :style="{ color: colorFor(item) }" />
                            <span class="text-truncate">{{ item.name }}</span>
                            <VibeBadge v-if="item.status === 'pending'" variant="info" class="ms-2"><VibeSpinner size="sm" class="me-1" />Processing</VibeBadge>
                            <VibeBadge v-else-if="item.status === 'infected'" variant="danger" class="ms-2"><VibeIcon icon="shield-exclamation" class="me-1" />Infected</VibeBadge>
                            <VibeBadge v-else-if="item.status === 'failed'" variant="danger" class="ms-2">Failed</VibeBadge>
                            <VibeBadge v-if="(item.version ?? 0) > 1" variant="secondary" class="ms-2">v{{ item.version }}</VibeBadge>
                        </div>
                        <div v-if="item.tags?.length" class="mt-1" style="padding-left: 2.75rem">
                            <span
                                v-for="t in item.tags"
                                :key="t.id"
                                class="badge rounded-pill me-1"
                                :style="{ backgroundColor: t.color || '#6c757d', cursor: 'pointer' }"
                                @click.stop="emit('tag', t.id)"
                            >{{ t.name }}</span>
                        </div>
                    </template>
                </template>
            </component>
        </template>
    </VibeDraggable>
</template>
