<script setup lang="ts">
import MarkdownViewer from './MarkdownViewer.vue';
import DocViewer from './DocViewer.vue';
import { iconFor, imageTypes } from '../lib/fileTypes';

interface QuickFile {
    id: number;
    name: string;
    type?: string;
    mime?: string;
    url?: string;
    size?: number;
    metadata?: { preview?: string };
}
interface ActionItem { text: string; icon: string; action: string }

const open = defineModel<boolean>({ required: true });
defineProps<{
    file: QuickFile | null;
    index: number;
    total: number;
    menu: ActionItem[];
}>();

const emit = defineEmits<{
    step: [number];
    action: [unknown];
}>();

const officeTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];
const previewMarkdownTypes = ['md', 'markdown'];

function isImage(f: QuickFile | null): boolean {
    return !!f && !!f.type && imageTypes.includes(f.type);
}
</script>

<template>
    <VibeModal v-model="open" fullscreen hide-footer>
        <template #header>
            <div class="d-flex align-items-center justify-content-between w-100">
                <h5 class="modal-title text-truncate mb-0">
                    <VibeIcon :icon="file ? iconFor(file.type) : 'file-earmark'" class="me-2" />
                    {{ file?.name }}
                </h5>
                <div class="d-flex gap-2 align-items-center ms-3">
                    <small class="text-muted">{{ index + 1 }} / {{ total }}</small>
                    <VibeButton variant="secondary" size="sm" outline title="Previous (←)" aria-label="Previous file" @click="emit('step', -1)">
                        <VibeIcon icon="chevron-left" />
                    </VibeButton>
                    <VibeButton variant="secondary" size="sm" outline title="Next (→)" aria-label="Next file" @click="emit('step', 1)">
                        <VibeIcon icon="chevron-right" />
                    </VibeButton>
                    <VibeButton variant="success" size="sm" :href="`/download/${file?.id}`">
                        <VibeIcon icon="download" class="me-1" />Download
                    </VibeButton>
                    <VibeDropdown
                        v-if="file"
                        variant="primary"
                        size="sm"
                        :items="menu"
                        @item-click="emit('action', $event)"
                    >
                        <VibeIcon icon="three-dots-vertical" class="me-1" />Actions
                    </VibeDropdown>
                    <VibeButton variant="secondary" size="sm" outline title="Close" aria-label="Close preview" @click="open = false">
                        <VibeIcon icon="x-lg" />
                    </VibeButton>
                </div>
            </div>
        </template>

        <div v-if="file" class="quicklook-body d-flex flex-column align-items-center justify-content-center text-center" style="height: calc(100vh - 130px)">
            <img
                v-if="isImage(file)"
                :src="file.url"
                :alt="file.name"
                class="img-fluid rounded"
                style="max-height: 100%; object-fit: contain"
            >
            <iframe
                v-else-if="file.type === 'pdf'"
                :src="file.url"
                class="w-100 h-100 border rounded"
            ></iframe>
            <audio v-else-if="file.mime?.startsWith('audio/')" :src="file.url" controls class="w-100" />
            <video
                v-else-if="file.mime?.startsWith('video/')"
                :src="file.url"
                controls
                class="img-fluid rounded"
                style="max-height: 100%"
            />
            <MarkdownViewer
                v-else-if="previewMarkdownTypes.includes(file.type ?? '')"
                :key="file.id"
                :url="`/raw/${file.id}`"
                class="w-100 h-100 overflow-auto text-start"
            />
            <DocViewer
                v-else-if="officeTypes.includes(file.type ?? '')"
                :key="file.id"
                :url="file.url ?? ''"
                :type="file.type ?? ''"
                class="w-100 h-100 overflow-auto text-start"
            />
            <pre
                v-else-if="file.metadata?.preview"
                class="text-start p-3 bg-body-tertiary rounded border w-100 h-100"
                style="overflow: auto; white-space: pre-wrap"
            >{{ file.metadata.preview }}</pre>
            <div v-else class="text-muted py-5">
                <VibeIcon :icon="iconFor(file.type)" class="display-1 d-block mb-3" />
                No inline preview for this file type.
                <div class="small mt-2">{{ file.mime || 'unknown type' }} · {{ ((file.size ?? 0) / 1024).toFixed(1) }} KB</div>
            </div>
        </div>
    </VibeModal>
</template>
