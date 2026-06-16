<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useColorMode } from '@velkymx/vibeui';
import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
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

// Resolve the effective theme (handles 'auto' via the html data-bs-theme attr).
function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

function build() {
    editor = new Editor({
        el: el.value,
        initialValue: props.modelValue,
        initialEditType: 'wysiwyg',
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
    });
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
    editor?.destroy();
    editor = null;
});
</script>

<template>
    <div ref="el" class="md-editor-fill"></div>
</template>

<style>
.md-editor-fill {
    height: calc(100vh - 170px);
    min-height: 360px;
}
</style>
