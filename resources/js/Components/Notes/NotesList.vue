<script setup>
defineProps({
    notes: { type: Array, default: () => [] },
    selectedId: { type: Number, default: null },
    sort: { type: String, default: 'name-asc' },
});
const emit = defineEmits(['select', 'new', 'update:sort']);

const sortOptions = [
    { value: 'name-asc', text: 'Name A–Z', icon: 'sort-alpha-down' },
    { value: 'name-desc', text: 'Name Z–A', icon: 'sort-alpha-up' },
    { value: 'date', text: 'Last edited', icon: 'clock-history' },
];
</script>

<template>
    <div class="notes-list d-flex flex-column h-100 border-end bg-body">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom gap-2">
            <span class="fw-semibold">Notes</span>
            <div class="d-flex align-items-center gap-1">
                <VibeDropdown
                    size="sm"
                    variant="light"
                    menu-end
                    title="Sort notes"
                    :items="sortOptions"
                    @item-click="emit('update:sort', $event.item.value)"
                >
                    <template #button><VibeIcon icon="sort-down" /></template>
                    <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                </VibeDropdown>
                <VibeButton size="sm" variant="primary" title="New note" @click="emit('new')">
                    <VibeIcon icon="plus-lg" />
                </VibeButton>
            </div>
        </div>

        <div class="flex-grow-1 overflow-auto">
            <p v-if="!notes.length" class="text-muted small px-3 py-4 text-center mb-0">
                No notes yet. Create your first one.
            </p>
            <button
                v-for="note in notes"
                :key="note.id"
                type="button"
                class="note-row w-100 text-start border-0 border-bottom px-3 py-2 bg-transparent"
                :class="{ active: note.id === selectedId }"
                @click="emit('select', note.id)"
            >
                <div class="fw-medium text-truncate">{{ note.title }}</div>
                <div class="small text-muted d-flex align-items-center gap-2">
                    <span>{{ note.updated_at }}</span>
                    <span v-for="tag in note.tags" :key="tag.id" class="badge text-bg-light">#{{ tag.name }}</span>
                </div>
            </button>
        </div>
    </div>
</template>

<style scoped>
.notes-list {
    width: 280px;
    flex-shrink: 0;
}
.note-row {
    cursor: pointer;
}
.note-row:hover {
    background: rgba(99, 102, 241, 0.06) !important;
}
.note-row.active {
    background: rgba(99, 102, 241, 0.12) !important;
}
</style>
