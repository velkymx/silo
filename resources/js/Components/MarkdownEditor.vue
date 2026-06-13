<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const textarea = ref(null);
let mde = null;

onMounted(() => {
    mde = new EasyMDE({
        element: textarea.value,
        initialValue: props.modelValue,
        spellChecker: false,
        status: ['lines', 'words'],
        autoDownloadFontAwesome: true,
        toolbar: [
            'bold', 'italic', 'heading', '|',
            'quote', 'unordered-list', 'ordered-list', '|',
            'link', 'image', 'code', 'table', '|',
            'preview', 'side-by-side', 'fullscreen', '|',
            'guide',
        ],
    });
    mde.codemirror.on('change', () => emit('update:modelValue', mde.value()));
});

watch(
    () => props.modelValue,
    (val) => {
        if (mde && val !== mde.value()) mde.value(val);
    }
);

onBeforeUnmount(() => {
    mde?.toTextArea();
    mde?.cleanup?.();
    mde = null;
});
</script>

<template>
    <textarea ref="textarea"></textarea>
</template>
