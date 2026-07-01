<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useCommandPalette } from '../composables/useCommandPalette';
import { useToast } from '../composables/useToast';
import ToastHost from '../Components/ToastHost.vue';
import CommandPalette from '../Components/CommandPalette.vue';
import FourPane from '../Components/FourPane.vue';
import GlobalRail from '../Components/GlobalRail.vue';

// Universal shell: every route mounts this layout. It owns only the
// app-wide overlays (toasts, command palette, skip-link) plus the
// FourPane frame with GlobalRail fixed in column 1 — nothing
// Files-specific (topbar search, saved searches, storage meter) lives
// here; those stay in AppLayout for not-yet-migrated pages.
const { toggle: togglePalette } = useCommandPalette();
const toast = useToast();

const activePane = ref('contents');

// Cmd/Ctrl-K toggles the command palette from any page (mirrors AppLayout).
function onKeydown(e: KeyboardEvent): void {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        const t = e.target as HTMLElement;
        if (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable) return;
        e.preventDefault();
        togglePalette();
    }
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="shell-layout">
        <a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 z-3 p-2 bg-primary text-white text-decoration-none rounded-bottom-end">Skip to main content</a>

        <FourPane v-model:activePane="activePane">
            <template #rail>
                <GlobalRail />
            </template>
            <template #folders>
                <slot name="viewNav" />
            </template>
            <template #contents>
                <div id="main-content" class="flex-grow-1 d-flex flex-column min-vh-0">
                    <slot name="contents" />
                </div>
            </template>
            <template #detail>
                <slot name="detail" />
            </template>
        </FourPane>

        <ToastHost
            :items="toast.state.items"
            @dismiss="toast.dismiss($event)"
            @undo="(t) => { t.undo?.(); toast.dismiss(t.id); }"
        />

        <CommandPalette />
    </div>
</template>
