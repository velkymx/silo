<script setup>
// Presentational autocomplete dropdown for [[wikilink]] / @mention suggestions.
// Positioning + keyboard handling live in the parent (MarkdownEditor); this
// component only renders the list and reports clicks.
defineProps({
    items: { type: Array, default: () => [] },
    activeIndex: { type: Number, default: 0 },
    type: { type: String, default: 'wiki' }, // 'wiki' | 'mention'
    top: { type: Number, default: 0 },
    left: { type: Number, default: 0 },
});
const emit = defineEmits(['pick', 'hover']);
</script>

<template>
    <ul
        v-if="items.length"
        class="note-popup list-unstyled shadow-sm border rounded bg-body"
        :style="{ top: top + 'px', left: left + 'px' }"
        role="listbox"
    >
        <li
            v-for="(item, i) in items"
            :key="item.id"
            role="option"
            :aria-selected="i === activeIndex"
            class="note-popup-item d-flex align-items-center gap-2 px-2 py-1"
            :class="{ active: i === activeIndex }"
            @mousedown.prevent="emit('pick', item)"
            @mouseenter="emit('hover', i)"
        >
            <VibeIcon :icon="type === 'wiki' ? 'journal-text' : 'at'" class="text-muted" />
            <span class="text-truncate">{{ type === 'wiki' ? item.title : item.name }}</span>
            <small v-if="type === 'mention'" class="text-muted ms-auto">@{{ item.handle }}</small>
        </li>
    </ul>
</template>

<style scoped>
.note-popup {
    position: absolute;
    z-index: 1080;
    min-width: 220px;
    max-width: 320px;
    max-height: 240px;
    overflow-y: auto;
    margin: 0;
    padding: 0.25rem 0;
}
.note-popup-item {
    cursor: pointer;
}
.note-popup-item.active,
.note-popup-item:hover {
    background: rgba(99, 102, 241, 0.12);
}
</style>
