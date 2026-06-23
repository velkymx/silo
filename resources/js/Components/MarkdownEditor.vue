<script setup>
import { onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useColorMode } from '@velkymx/vibeui';
import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';
import { detectTrigger, insertionFor } from '../lib/noteTriggers';
import { http } from '../lib/http';
import MentionPopup from './Notes/MentionPopup.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    // When set, the editor opens in markdown mode and enables [[ / @ autocomplete.
    enableLinks: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const el = ref(null);
let editor = null;
// The last markdown WE emitted. Compared against incoming modelValue so the
// editor's own output is never fed back into it — Toast UI normalizes markdown
// in WYSIWYG, so comparing against getMarkdown() falsely fires setMarkdown(),
// which rebuilds the document and drops toolbar formatting / jumps the caret.
let lastEmitted = props.modelValue;
const { colorMode } = useColorMode();

// ---- Autocomplete state (only used when enableLinks) ----
const popup = reactive({ open: false, items: [], type: 'wiki', activeIndex: 0, top: 0, left: 0 });
let trigger = null; // { caret: { line, ch }, matchLen }
let fetchTimer = null;

// Resolve the effective theme (handles 'auto' via the html data-bs-theme attr).
function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

function build() {
    editor = new Editor({
        el: el.value,
        initialValue: props.modelValue,
        initialEditType: props.enableLinks ? 'markdown' : 'wysiwyg',
        previewStyle: 'tab',
        height: '100%',
        usageStatistics: false,
        theme: isDark() ? 'dark' : 'light',
        toolbarItems: [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'image', 'link'],
            ['code', 'codeblock'],
        ],
    });
    editor.on('change', () => {
        lastEmitted = editor.getMarkdown();
        emit('update:modelValue', lastEmitted);
        if (props.enableLinks) detectAtCaret();
    });

    if (props.enableLinks) {
        el.value.addEventListener('keyup', onKeyup, true);
        el.value.addEventListener('keydown', onKeydown, true);
    }
}

onMounted(build);

watch(
    () => props.modelValue,
    (val) => {
        // Apply only genuinely external changes, never our own emit.
        if (editor && val !== lastEmitted) {
            lastEmitted = val;
            editor.setMarkdown(val, false);
        }
    }
);

// Toast UI's dark theme is purely the `toastui-editor-dark` class on its root.
// Toggle it live on color-mode flips instead of destroying/rebuilding the
// editor (which would lose the caret, scroll, and undo history mid-edit).
watch(colorMode, () => {
    const root = el.value?.querySelector('.toastui-editor-defaultUI');
    root?.classList.toggle('toastui-editor-dark', isDark());
});

onBeforeUnmount(() => {
    if (props.enableLinks && el.value) {
        el.value.removeEventListener('keyup', onKeyup, true);
        el.value.removeEventListener('keydown', onKeydown, true);
    }
    editor?.destroy();
    editor = null;
});

// ---- Autocomplete behaviour ----

// Inspect the caret line prefix for an active [[ or @ trigger.
function detectAtCaret() {
    const sel = editor.getSelection?.();
    if (!Array.isArray(sel) || !Array.isArray(sel[1])) {
        return closePopup();
    }
    const [line, ch] = sel[1];
    const lineText = (editor.getMarkdown().split('\n')[line] ?? '');
    const trig = detectTrigger(lineText.slice(0, ch));

    if (!trig || trig.query.length < 1) {
        return closePopup();
    }

    popup.type = trig.type;
    trigger = { caret: { line, ch }, matchLen: trig.matchLen };
    positionPopup();
    scheduleFetch(trig.type, trig.query);
}

function scheduleFetch(type, query) {
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(async () => {
        const url = type === 'wiki'
            ? `/notes/search/notes?q=${encodeURIComponent(query)}`
            : `/notes/search/users?q=${encodeURIComponent(query)}`;
        try {
            const data = await http.get(url);
            popup.items = data?.results ?? [];
            popup.activeIndex = 0;
            popup.open = popup.items.length > 0;
        } catch {
            closePopup();
        }
    }, 150);
}

// Anchor the popup near the caret; degrade gracefully where ranges are absent.
function positionPopup() {
    try {
        const range = window.getSelection()?.getRangeAt(0);
        const caretRect = range?.getBoundingClientRect();
        const hostRect = el.value?.getBoundingClientRect();
        if (caretRect && hostRect && (caretRect.top || caretRect.left)) {
            popup.top = caretRect.bottom - hostRect.top + 4;
            popup.left = caretRect.left - hostRect.left;
            return;
        }
    } catch {
        /* no selection range (e.g. jsdom) */
    }
    popup.top = 24;
    popup.left = 8;
}

function onKeyup(e) {
    // Navigation keys are handled on keydown; ignore them here.
    if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab'].includes(e.key)) return;
    detectAtCaret();
}

function onKeydown(e) {
    if (!popup.open) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        popup.activeIndex = (popup.activeIndex + 1) % popup.items.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        popup.activeIndex = (popup.activeIndex - 1 + popup.items.length) % popup.items.length;
    } else if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        pick(popup.items[popup.activeIndex]);
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closePopup();
    }
}

// Replace the in-progress trigger token with the chosen suggestion.
function pick(item) {
    if (!item || !trigger) return;
    const value = popup.type === 'wiki' ? item.title : item.handle;
    const { line, ch } = trigger.caret;
    editor.setSelection([line, ch - trigger.matchLen], [line, ch]);
    editor.replaceSelection(insertionFor(popup.type, value));
    closePopup();
    editor.focus?.();
}

function closePopup() {
    popup.open = false;
    popup.items = [];
    trigger = null;
}

// Move the caret to a given line and scroll it into view — used by the outline
// to jump between headings.
function jumpToLine(line) {
    if (!editor) return;
    editor.setSelection?.([line, 0], [line, 0]);
    editor.focus?.();
}

defineExpose({ jumpToLine });
</script>

<template>
    <div class="md-editor-wrap position-relative" :class="{ 'md-editor-fill-host': enableLinks }">
        <div ref="el" class="md-editor-fill"></div>
        <MentionPopup
            v-if="enableLinks && popup.open"
            :items="popup.items"
            :active-index="popup.activeIndex"
            :type="popup.type"
            :top="popup.top"
            :left="popup.left"
            @pick="pick"
            @hover="popup.activeIndex = $event"
        />
    </div>
</template>

<style>
.md-editor-fill {
    height: calc(100vh - 170px);
    min-height: 360px;
}

/* In the Notes surface the editor fills its flex container instead of a fixed
   viewport slice, so it stretches the full height of the 3-pane layout. */
.md-editor-fill-host {
    height: 100%;
    min-height: 0;
}
.md-editor-fill-host .md-editor-fill {
    height: 100%;
    min-height: 0;
}
</style>
