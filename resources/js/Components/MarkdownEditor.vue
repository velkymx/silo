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
    editor.on('change', () => emit('update:modelValue', editor.getMarkdown()));
}

onMounted(build);

watch(
    () => props.modelValue,
    (val) => {
        if (editor && val !== editor.getMarkdown()) editor.setMarkdown(val, false);
    }
);

// Toast UI bakes the theme in at construction — rebuild on color-mode flip.
watch(colorMode, () => {
    if (!editor) return;
    const current = editor.getMarkdown();
    editor.destroy();
    editor = null;
    build();
    editor.setMarkdown(current, false);
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
