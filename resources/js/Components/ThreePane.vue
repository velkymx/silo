<script setup>
defineProps({
    sidebarWidth: { type: String, default: '230px' },
    listWidth: { type: String, default: '340px' },
});

const activePane = defineModel('activePane', { default: 'list' });

function goBack() {
    if (activePane.value === 'detail') activePane.value = 'list';
    else if (activePane.value === 'list') activePane.value = 'sidebar';
}
</script>

<template>
    <div class="three-pane d-flex border rounded overflow-hidden bg-body">
        <!-- Mobile nav bar: back chevron, shown only when not in sidebar -->
        <div v-if="activePane !== 'sidebar'" class="tp-mobile-nav d-flex d-md-none align-items-center px-2 py-1 border-bottom bg-body-tertiary flex-shrink-0">
            <button type="button" class="btn btn-sm btn-link p-0 text-body text-decoration-none" @click="goBack">
                <VibeIcon icon="chevron-left" />
                <span class="ms-1">Back</span>
            </button>
        </div>

        <div
            class="tp-sidebar h-100 border-end bg-body-tertiary"
            :class="{ 'tp-pane--hidden': activePane !== 'sidebar' }"
            :style="{ width: sidebarWidth }"
        >
            <slot name="sidebar" />
        </div>
        <div
            class="tp-list h-100 border-end bg-body d-flex flex-column"
            :class="{ 'tp-pane--hidden': activePane !== 'list' }"
            :style="{ width: listWidth }"
        >
            <slot name="list" />
        </div>
        <div
            class="tp-detail flex-grow-1 d-flex flex-column"
            :class="{ 'tp-pane--hidden': activePane !== 'detail' }"
            style="min-width: 0; min-height: 0"
        >
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

/* Mobile: show one pane at a time. */
@media (max-width: 767.98px) {
    .three-pane {
        flex-direction: column;
        height: calc(100vh - 100px);
    }
    .tp-sidebar,
    .tp-list,
    .tp-detail {
        width: 100% !important;
        height: 100%;
        flex-shrink: 0;
    }
    .tp-pane--hidden {
        display: none !important;
    }
    .tp-mobile-nav {
        width: 100%;
    }
}
</style>
