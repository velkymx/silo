<script setup>
import EmptyState from '../EmptyState.vue';
defineProps({
    notes: { type: Array, default: () => [] },
    selectedId: { type: Number, default: null },
    sort: { type: String, default: 'name-asc' },
    selectMode: { type: Boolean, default: false },
    isSelected: { type: Function, default: () => false },
});
const emit = defineEmits(['select', 'new', 'update:sort', 'toggle-select', 'update:select-mode']);

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
                <VibeButton
                    size="sm"
                    :variant="selectMode ? 'primary' : 'secondary'"
                    outline
                    title="Select notes"
                    aria-label="Select notes"
                    @click="emit('update:select-mode', !selectMode)"
                >
                    <VibeIcon icon="check2-square" />
                </VibeButton>
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
                <VibeButton size="sm" variant="primary" title="New note" aria-label="New note" @click="emit('new')">
                    <VibeIcon icon="plus-lg" />
                </VibeButton>
            </div>
        </div>

        <div class="flex-grow-1 overflow-auto">
            <EmptyState v-if="!notes.length" icon="journal-text" title="No notes yet" hint="Create your first note to get started." />
            <button
                v-for="note in notes"
                :key="note.id"
                type="button"
                class="note-row w-100 text-start border-0 border-bottom px-3 py-2 bg-transparent d-flex align-items-start gap-2"
                :class="{ active: note.id === selectedId, 'bg-primary-subtle': isSelected(note.id) }"
                @click="selectMode ? emit('toggle-select', note.id) : emit('select', note.id)"
            >
                <VibeIcon
                    v-if="selectMode"
                    :icon="isSelected(note.id) ? 'check-circle-fill' : 'circle'"
                    class="mt-1 flex-shrink-0"
                    :class="isSelected(note.id) ? 'text-primary' : 'text-muted'"
                />
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-medium text-truncate d-flex align-items-center gap-1">
                        <VibeIcon v-if="note.starred" icon="star-fill" class="text-warning small" title="Starred" />
                        <span class="text-truncate">{{ note.title }}</span>
                    </div>
                    <div class="small text-muted d-flex align-items-center gap-2">
                        <span>{{ note.updated_at }}</span>
                        <span v-for="tag in note.tags" :key="tag.id" class="badge text-bg-light">#{{ tag.name }}</span>
                    </div>
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
