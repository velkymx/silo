<script setup lang="ts">
const props = withDefaults(defineProps<{
    /** Render the detail column. When false the contents column fills the row. */
    detailVisible?: boolean;
}>(), { detailVisible: true });

const activePane = defineModel<string>('activePane', { default: 'contents' });

// Mobile shows one pane at a time; the back control walks left through the chain.
const order = ['rail', 'folders', 'contents', 'detail'];

function goBack(): void {
    const i = order.indexOf(activePane.value);
    if (i > 0) activePane.value = order[i - 1];
}
</script>

<template>
    <div class="four-pane d-flex flex-column bg-body">
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

        <VibeRow class="fp-grid g-0 flex-nowrap flex-grow-1">
            <VibeCol
                cols="auto"
                class="fp-rail h-100 border-end bg-body-tertiary"
                data-pane="rail"
                :class="{ 'fp-pane--hidden': activePane !== 'rail' }"
            >
                <slot name="rail" />
            </VibeCol>
            <VibeCol
                :cols="12"
                :md="2"
                class="fp-folders h-100 border-end bg-body-tertiary"
                data-pane="folders"
                :class="{ 'fp-pane--hidden': activePane !== 'folders' }"
            >
                <slot name="folders" />
            </VibeCol>
            <!-- Right region: an optional top bar spanning contents + detail. -->
            <VibeCol
                :cols="12"
                :md="true"
                class="fp-right h-100 d-flex flex-column"
                :class="{ 'fp-pane--hidden': activePane === 'rail' || activePane === 'folders' }"
            >
                <div v-if="$slots.topBar" class="fp-topbar flex-shrink-0 border-bottom bg-body">
                    <slot name="topBar" />
                </div>
                <VibeRow class="fp-grid g-0 flex-nowrap flex-grow-1">
                    <VibeCol
                        :cols="12"
                        :md="true"
                        class="fp-contents h-100 bg-body d-flex flex-column"
                        data-pane="contents"
                        :class="{ 'fp-pane--hidden': activePane !== 'contents', 'border-end': props.detailVisible }"
                    >
                        <slot name="contents" />
                    </VibeCol>
                    <!-- Preview dominates when open: 7/12 (~60%) of the right
                         region, the file list keeps the remaining ~40%. -->
                    <VibeCol
                        v-if="props.detailVisible"
                        :cols="12"
                        :md="7"
                        class="fp-detail h-100 bg-body d-flex flex-column"
                        data-pane="detail"
                        :class="{ 'fp-pane--hidden': activePane !== 'detail' }"
                    >
                        <slot name="detail" />
                    </VibeCol>
                </VibeRow>
            </VibeCol>
        </VibeRow>
    </div>
</template>

<style scoped>
.four-pane {
    height: 100%;
    min-height: 0;
}
.fp-grid {
    min-height: 0;
}
/* Scoped styles reach the VibeCol root elements (child component roots
   inherit the parent scope id), so pane columns can be sized here. */
.fp-rail,
.fp-folders {
    overflow-y: auto;
    min-height: 0;
}
.fp-right,
.fp-contents,
.fp-detail {
    min-width: 0;
    min-height: 0;
}
.fp-detail {
    overflow-y: auto;
}
.fp-pane--hidden {
    display: none !important;
}

/* Desktop shows every pane; the hidden class only applies on mobile. */
@media (min-width: 768px) {
    .fp-pane--hidden.fp-rail,
    .fp-pane--hidden.fp-folders {
        display: block !important;
    }
    .fp-pane--hidden.fp-right,
    .fp-pane--hidden.fp-contents,
    .fp-pane--hidden.fp-detail {
        display: flex !important;
    }
}

/* Mobile: one pane at a time, full width. */
@media (max-width: 767.98px) {
    .four-pane {
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
