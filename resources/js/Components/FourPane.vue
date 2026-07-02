<script setup lang="ts">
const props = defineProps<{
    railWidth?: string;
    foldersWidth?: string;
    contentsWidth?: string;
}>();

const railWidth = props.railWidth ?? '56px';
const foldersWidth = props.foldersWidth ?? '240px';
const contentsWidth = props.contentsWidth ?? '360px';

const activePane = defineModel<string>('activePane', { default: 'contents' });

// Mobile shows one pane at a time; the back control walks left through the chain.
const order = ['rail', 'folders', 'contents', 'detail'];

function goBack(): void {
    const i = order.indexOf(activePane.value);
    if (i > 0) activePane.value = order[i - 1];
}
</script>

<template>
    <div class="four-pane d-flex overflow-hidden bg-body">
        <!-- Mobile nav bar: back chevron, hidden on the first (rail) pane. -->
        <div
            v-if="activePane !== 'rail'"
            class="fp-mobile-nav d-flex d-md-none align-items-center px-2 py-1 border-bottom bg-body-tertiary flex-shrink-0"
        >
            <VibeButton variant="link" size="sm" class="p-0 text-body text-decoration-none" data-testid="fp-back" @click="goBack">
                <VibeIcon icon="chevron-left" />
                <span class="ms-1">Back</span>
            </VibeButton>
        </div>

        <div
            class="fp-rail h-100 border-end bg-body-tertiary"
            data-pane="rail"
            :class="{ 'fp-pane--hidden': activePane !== 'rail' }"
            :style="{ width: railWidth }"
        >
            <slot name="rail" />
        </div>
        <div
            class="fp-folders h-100 border-end bg-body-tertiary"
            data-pane="folders"
            :class="{ 'fp-pane--hidden': activePane !== 'folders' }"
            :style="{ width: foldersWidth }"
        >
            <slot name="folders" />
        </div>
        <!-- Right region: an optional top bar spanning contents + detail. -->
        <div
            class="fp-right flex-grow-1 d-flex flex-column"
            :class="{ 'fp-pane--hidden': activePane === 'rail' || activePane === 'folders' }"
            style="min-width: 0; min-height: 0"
        >
            <div v-if="$slots.topBar" class="fp-topbar flex-shrink-0 border-bottom bg-body">
                <slot name="topBar" />
            </div>
            <div class="d-flex flex-grow-1" style="min-height: 0">
                <div
                    class="fp-contents h-100 border-end bg-body d-flex flex-column"
                    data-pane="contents"
                    :class="{ 'fp-pane--hidden': activePane !== 'contents' }"
                    :style="{ width: contentsWidth }"
                >
                    <slot name="contents" />
                </div>
                <div
                    class="fp-detail flex-grow-1 d-flex flex-column"
                    data-pane="detail"
                    :class="{ 'fp-pane--hidden': activePane !== 'detail' }"
                    style="min-width: 0; min-height: 0"
                >
                    <slot name="detail" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.four-pane {
    height: 100%;
    min-height: 0;
}
.fp-rail,
.fp-folders {
    flex-shrink: 0;
    overflow-y: auto;
}
.fp-contents {
    flex-shrink: 0;
}
.fp-pane--hidden {
    display: none !important;
}

/* Desktop shows every pane; the hidden class only applies on mobile. */
@media (min-width: 768px) {
    .fp-pane--hidden {
        display: flex !important;
    }
}

/* Mobile: one pane at a time, full width. */
@media (max-width: 767.98px) {
    .four-pane {
        flex-direction: column;
        height: calc(100dvh - 100px);
    }
    .fp-rail,
    .fp-folders,
    .fp-contents,
    .fp-detail {
        width: 100% !important;
        height: 100%;
        flex-shrink: 0;
    }
    .fp-mobile-nav {
        width: 100%;
    }
}
</style>
