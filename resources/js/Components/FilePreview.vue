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
    <div class="file-preview d-flex flex-column">
        <img
            v-if="isImageType(file.type)"
            :src="safeUrl(file.url)"
            :alt="file.name"
            class="img-fluid rounded border"
            loading="lazy"
            style="max-height: 320px; object-fit: contain; background: var(--bs-tertiary-bg)"
        >
        <iframe
            v-else-if="file.type === 'pdf'"
            :src="safeUrl(file.url)"
            :title="`PDF preview of ${file.name}`"
            class="w-100 border rounded"
            style="height: 360px"
        ></iframe>
        <audio v-else-if="file.mime?.startsWith('audio/')" :src="safeUrl(file.url)" controls class="w-100" />
        <video v-else-if="file.mime?.startsWith('video/')" :src="safeUrl(file.url)" controls class="img-fluid rounded" style="max-height: 320px" />
        <MarkdownViewer
            v-else-if="previewMarkdownTypes.includes(file.type ?? '')"
            :key="file.id"
            :url="`/raw/${file.id}`"
            class="w-100 overflow-auto text-start border rounded p-2"
            style="max-height: 360px"
        />
        <DocViewer
            v-else-if="officeTypes.includes(file.type ?? '')"
            :key="file.id"
            :url="safeUrl(file.url)"
            :type="file.type ?? ''"
            class="w-100 overflow-auto text-start border rounded"
            style="max-height: 360px"
        />
        <pre
            v-else-if="file.metadata?.preview"
            class="text-start p-2 bg-body-tertiary rounded border w-100"
            style="max-height: 360px; overflow: auto; white-space: pre-wrap"
        >{{ file.metadata.preview }}</pre>
        <div v-else class="text-muted text-center py-5 border rounded" style="background: var(--bs-tertiary-bg)">
            <VibeIcon :icon="iconFor(file.type)" class="display-3 d-block mb-2" />
            <div class="small">No inline preview · {{ file.mime || 'unknown type' }} · {{ fmtBytes(file.size ?? 0) }}</div>
        </div>
    </div>
</template>
