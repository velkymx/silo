<script setup lang="ts">
import { computed } from 'vue';
import { iconFor, colorFor } from '../lib/fileTypes';
import { fmtBytes } from '../lib/format';
import { FileStatus } from '../lib/constants';
import ItemActions from './ItemActions.vue';

// Pick black or white foreground based on the background luminance so
// the tag badge always has readable contrast (WCAG 2.1 SC 1.4.11).
function readableFg(hex?: string): string {
    if (!hex) return '#fff';
    const m = hex.replace('#', '');
    const r = parseInt(m.slice(0, 2), 16);
    const g = parseInt(m.slice(2, 4), 16);
    const b = parseInt(m.slice(4, 6), 16);
    // Relative luminance per WCAG.
    const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return lum > 0.55 ? '#000' : '#fff';
}

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
    context: [{ item: FileItemData; event: MouseEvent }];
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
                        @contextmenu.prevent="emit('context', { item, event: $event })"
                    >
                        <VibeButton
                            v-if="selectMode || selected"
                            variant="light"
                            size="sm"
                            class="grid-select position-absolute top-0 start-0 m-1 p-0 lh-1 bg-body rounded-circle"
                            :class="selected ? 'text-primary' : 'text-muted'"
                            :aria-pressed="selected"
                            :aria-label="selected ? `Deselect ${item.name}` : `Select ${item.name}`"
                            style="z-index: 3"
                            @click.stop="emit('toggle-select', item.id)"
                        >
                            <VibeIcon :icon="selected ? 'check-circle-fill' : 'circle'" />
                        </VibeButton>
                        <div class="position-absolute top-0 end-0 m-1" style="z-index: 2">
                            <ItemActions :item="item" :menu="menu" @star="emit('star', item)" @action="emit('action', $event)" />
                        </div>
                        <button
                            type="button"
                            class="card-body-btn"
                            :aria-label="item.name"
                            @click="emit('open', item, $event)"
                        >
                            <div class="d-flex align-items-center justify-content-center bg-body-tertiary rounded-top" style="height: 120px">
                                <img v-if="item.thumb_url" :src="item.thumb_url" :alt="item.name" class="w-100 h-100" style="object-fit: cover">
                                <VibeIcon v-else :icon="item.is_dir ? 'folder2' : iconFor(item.type)" class="display-4" :style="item.is_dir ? undefined : { color: colorFor(item) }" />
                            </div>
                            <div class="p-2">
                                <div class="text-truncate small fw-medium" :title="item.name">{{ item.name }}</div>
                                <div class="text-muted" style="font-size: 0.7rem">
                                    {{ item.is_dir ? `${item.item_count} items` : fmtBytes(item.size ?? 0) }}
                                </div>
                                <VibeBadge v-if="item.status === FileStatus.PENDING" variant="info" class="mt-1">Processing</VibeBadge>
                                <VibeBadge v-else-if="item.status === FileStatus.INFECTED" variant="danger" class="mt-1"><VibeIcon icon="shield-exclamation" class="me-1" />Infected</VibeBadge>
                                <VibeBadge v-else-if="item.status === FileStatus.FAILED" variant="danger" class="mt-1">Failed</VibeBadge>
                            </div>
                        </button>
                    </div>

                    <!-- LIST: name cell -->
                    <template v-else>
                        <button
                            type="button"
                            class="list-row d-flex align-items-center rounded w-100 border-0 bg-transparent text-body text-start p-0"
                            :class="{ 'opacity-50': isDragging, 'bg-primary-subtle': (drop && drop.isOver) || selected }"
                            @click="emit('open', item, $event)"
                            @contextmenu.prevent="emit('context', { item, event: $event })"
                        >
                            <VibeIcon
                                :icon="selected ? 'check-square-fill' : 'square'"
                                class="me-2 flex-shrink-0 select-check"
                                :class="selected ? 'text-primary' : 'text-muted'"
                                @click.stop="emit('toggle-select', item.id)"
                            />
                            <img v-if="item.thumb_url" :src="item.thumb_url" :alt="item.name" class="rounded border me-2 flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover">
                            <VibeIcon v-else :icon="item.is_dir ? 'folder2' : iconFor(item.type)" class="me-2 fs-4 flex-shrink-0" :style="item.is_dir ? undefined : { color: colorFor(item) }" />
                            <span class="text-truncate" :title="item.name">{{ item.name }}</span>
                            <VibeBadge v-if="item.status === FileStatus.PENDING" variant="info" class="ms-2"><VibeSpinner size="sm" class="me-1" />Processing</VibeBadge>
                            <VibeBadge v-else-if="item.status === FileStatus.INFECTED" variant="danger" class="ms-2"><VibeIcon icon="shield-exclamation" class="me-1" />Infected</VibeBadge>
                            <VibeBadge v-else-if="item.status === FileStatus.FAILED" variant="danger" class="ms-2">Failed</VibeBadge>
                            <VibeBadge v-if="(item.version ?? 0) > 1" variant="secondary" class="ms-2">v{{ item.version }}</VibeBadge>
                        </button>
                        <div v-if="item.tags?.length" class="d-flex flex-wrap gap-1 mt-1 ms-5">
                            <span
                                v-for="t in item.tags"
                                :key="t.id"
                                class="badge rounded-pill"
                                :style="{ backgroundColor: t.color || '#6c757d', color: readableFg(t.color || '#6c757d'), cursor: 'pointer' }"
                                @click.stop="emit('tag', t.id)"
                            >{{ t.name }}</span>
                        </div>
                    </template>
                </template>
            </component>
        </template>
    </VibeDraggable>
</template>

<style scoped>
/* Subtle lift on grid cards for tactile hover feedback. */
.card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12) !important;
}
/* Primary open button fills the card body; resets all button chrome. */
.card-body-btn {
    display: block;
    width: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: inherit;
    cursor: pointer;
}
.card-body-btn:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: -2px;
    border-radius: 0 0 var(--bs-card-border-radius, 0.375rem) var(--bs-card-border-radius, 0.375rem);
}
/* Outside select mode the list checkbox stays hidden until the row is hovered
   or focused, so it doesn't compete with the filename. */
.select-check.check-idle {
    opacity: 0;
    transition: opacity 0.15s;
}
.list-row:hover .select-check.check-idle,
.list-row:focus-within .select-check.check-idle {
    opacity: 1;
}
button.list-row {
    cursor: pointer;
}
button.list-row:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: 1px;
}
</style>
