<script setup>
// Universal three-column workspace shell (sidebar | list | detail) shared by
// the Notes, Bookmarks (and future) surfaces. Pure layout — content comes from
// the named slots, so each surface stays in charge of its own rows/detail.
defineProps({
    sidebarWidth: { type: String, default: '230px' },
    listWidth: { type: String, default: '340px' },
});
</script>

<template>
    <div class="three-pane d-flex border rounded overflow-hidden bg-body">
        <div class="tp-sidebar h-100 border-end bg-body-tertiary" :style="{ width: sidebarWidth }">
            <slot name="sidebar" />
        </div>
        <div class="tp-list h-100 border-end bg-body d-flex flex-column" :style="{ width: listWidth }">
            <slot name="list" />
        </div>
        <div class="tp-detail flex-grow-1 d-flex flex-column" style="min-width: 0; min-height: 0">
            <slot name="detail" />
        </div>
    </div>
</template>

<style scoped>
.three-pane {
    height: calc(100vh - 140px);
    min-height: 480px;
}
.tp-sidebar {
    flex-shrink: 0;
    overflow-y: auto;
}
.tp-list {
    flex-shrink: 0;
}
</style>
