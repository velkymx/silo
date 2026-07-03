<script setup lang="ts">
import MarkdownViewer from './MarkdownViewer.vue';
import DocViewer from './DocViewer.vue';
import { iconFor, isImageType } from '../lib/fileTypes';
import { fmtBytes } from '../lib/format';

interface PreviewFile {
    id: number;
    name: string;
    type?: string;
    mime?: string;
    url?: string;
    size?: number;
    metadata?: { preview?: string };
}

defineProps<{ file: PreviewFile }>();

const officeTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];
const previewMarkdownTypes = ['md', 'markdown'];

function safeUrl(url: string | undefined): string {
    if (!url) return '';
    try {
        const u = new URL(url, window.location.origin);
        return (u.protocol === 'https:' || u.protocol === 'http:' || u.protocol === 'blob:') ? url : '';
    } catch {
        return url.startsWith('/') ? url : '';
    }
}
</script>

<template>
    <!-- Every viewer stretches to the height the consumer gives the preview;
         sizing (flex-grow/h-100) is the consumer's job, filling is ours. -->
    <div class="file-preview d-flex flex-column">
        <img
            v-if="isImageType(file.type)"
            :src="safeUrl(file.url)"
            :alt="file.name"
            class="fp-fill rounded border w-100"
            loading="lazy"
            style="object-fit: contain; background: var(--bs-tertiary-bg)"
        >
        <iframe
            v-else-if="file.type === 'pdf'"
            :src="safeUrl(file.url)"
            :title="`PDF preview of ${file.name}`"
            class="fp-fill w-100 border rounded"
        ></iframe>
        <audio v-else-if="file.mime?.startsWith('audio/')" :src="safeUrl(file.url)" controls class="w-100 my-auto" />
        <video
            v-else-if="file.mime?.startsWith('video/')"
            :src="safeUrl(file.url)"
            controls
            class="fp-fill rounded w-100"
            style="object-fit: contain; background: var(--bs-tertiary-bg)"
        />
        <MarkdownViewer
            v-else-if="previewMarkdownTypes.includes(file.type ?? '')"
            :key="file.id"
            :url="`/raw/${file.id}`"
            class="fp-fill w-100 overflow-auto text-start border rounded p-2"
        />
        <DocViewer
            v-else-if="officeTypes.includes(file.type ?? '')"
            :key="file.id"
            :url="safeUrl(file.url)"
            :type="file.type ?? ''"
            class="fp-fill w-100 overflow-auto text-start border rounded"
        />
        <pre
            v-else-if="file.metadata?.preview"
            class="fp-fill text-start p-2 bg-body-tertiary rounded border w-100 mb-0"
            style="overflow: auto; white-space: pre-wrap"
        >{{ file.metadata.preview }}</pre>
        <div
            v-else
            class="fp-fill d-flex flex-column align-items-center justify-content-center text-muted text-center border rounded w-100"
            style="background: var(--bs-tertiary-bg)"
        >
            <VibeIcon :icon="iconFor(file.type)" class="display-3 d-block mb-2" />
            <div class="small">No inline preview · {{ file.mime || 'unknown type' }} · {{ fmtBytes(file.size ?? 0) }}</div>
        </div>
    </div>
</template>

<style scoped>
/* Grow to fill the preview area; min-height 0 lets the viewer shrink and
   scroll inside a bounded flex column instead of pushing the info block. */
.fp-fill {
    flex: 1 1 auto;
    min-height: 0;
}
</style>
