<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick } from 'vue';

interface MenuItem { text?: string; icon?: string; action?: string; divider?: boolean }

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ x: number; y: number; items: MenuItem[] }>();
const emit = defineEmits<{ select: [MenuItem] }>();

const menuEl = ref<HTMLElement | null>(null);
const pos = ref({ left: 0, top: 0 });
let returnFocus: HTMLElement | null = null;

function focusableItems(): HTMLElement[] {
    return Array.from(menuEl.value?.querySelectorAll<HTMLElement>('[role="menuitem"]:not(:disabled)') ?? []);
}

watch(open, async (v) => {
    if (v) {
        returnFocus = document.activeElement as HTMLElement;
        pos.value = { left: props.x, top: props.y };
        await nextTick();
        const el = menuEl.value;
        if (el) {
            const { width, height } = el.getBoundingClientRect();
            pos.value = {
                left: Math.min(props.x, window.innerWidth - width - 8),
                top: Math.min(props.y, window.innerHeight - height - 8),
            };
        }
        await nextTick();
        focusableItems()[0]?.focus();
        document.addEventListener('keydown', onKey);
        window.addEventListener('scroll', close, true);
    } else {
        document.removeEventListener('keydown', onKey);
        window.removeEventListener('scroll', close, true);
        returnFocus?.focus();
    }
});

function close(): void {
    open.value = false;
}
function onSelect(item: MenuItem): void {
    if (item.divider) return;
    emit('select', item);
    close();
}
function onKey(e: KeyboardEvent): void {
    if (e.key === 'Escape') { close(); return; }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(e.key)) return;
    e.preventDefault();
    const items = focusableItems();
    if (!items.length) return;
    const cur = items.indexOf(document.activeElement as HTMLElement);
    let next = cur;
    if (e.key === 'ArrowDown') next = cur < items.length - 1 ? cur + 1 : 0;
    else if (e.key === 'ArrowUp') next = cur > 0 ? cur - 1 : items.length - 1;
    else if (e.key === 'Home') next = 0;
    else if (e.key === 'End') next = items.length - 1;
    items[next]?.focus();
}

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', close, true);
});
</script>

<template>
    <teleport to="body">
        <template v-if="open">
            <!-- Backdrop captures the outside click (right or left) to dismiss. -->
            <div class="ctx-backdrop" @click="close" @contextmenu.prevent="close"></div>
            <ul
                ref="menuEl"
                class="dropdown-menu show ctx-menu"
                :style="{ left: pos.left + 'px', top: pos.top + 'px' }"
                role="menu"
            >
                <li v-for="(item, i) in items" :key="item.action ?? item.text ?? i">
                    <hr v-if="item.divider" class="dropdown-divider">
                    <button v-else type="button" role="menuitem" class="dropdown-item d-flex align-items-center" @click="onSelect(item)">
                        <VibeIcon v-if="item.icon" :icon="item.icon" class="me-2" />{{ item.text }}
                    </button>
                </li>
            </ul>
        </template>
    </teleport>
</template>

<style scoped>
.ctx-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1080;
}
.ctx-menu {
    position: fixed;
    z-index: 1081;
    display: block;
    min-width: 11rem;
}
</style>
