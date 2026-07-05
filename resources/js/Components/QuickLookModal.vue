<script setup lang="ts">
import { ref, watch } from 'vue';
import MarkdownViewer from './MarkdownViewer.vue';
import DocViewer from './DocViewer.vue';
import { iconFor, isImageType } from '../lib/fileTypes';
import { fmtBytes } from '../lib/format';

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
const props = withDefaults(defineProps<{
    file: QuickFile | null;
    index: number;
    total: number;
    menu: ActionItem[];
    prevFile?: (QuickFile & { thumb_url?: string | null }) | null;
    nextFile?: (QuickFile & { thumb_url?: string | null }) | null;
}>(), { prevFile: null, nextFile: null });

const hoverSide = ref<'prev' | 'next' | null>(null);
const loading = ref(true);
const loadError = ref('');

const emit = defineEmits<{
    step: [number];
    action: [unknown];
}>();

const officeTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];
const previewMarkdownTypes = ['md', 'markdown'];

// Reset the per-file load state when the parent navigates to a different
// file (via step emit) so the spinner + error come back for each load.
watch(() => props.file?.id, () => { loading.value = true; loadError.value = ''; });

function isImage(f: QuickFile | null): boolean {
    return isImageType(f?.type);
}

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
    <VibeModal v-model="open" fullscreen hide-footer>
        <template #header>
            <div class="ql-header d-flex align-items-center flex-grow-1 min-w-0">
                <h5 class="modal-title text-truncate mb-0 flex-grow-1 min-w-0">
                    <VibeIcon :icon="file ? iconFor(file.type) : 'file-earmark'" class="me-2" />
                    {{ file?.name }}
                </h5>
                <div class="d-flex gap-2 align-items-center ms-auto flex-shrink-0 me-2">
                    <small class="text-muted">{{ index + 1 }} / {{ total }}</small>
                    <div class="position-relative" @mouseenter="hoverSide = 'prev'" @mouseleave="hoverSide = null">
                        <VibeButton variant="light" size="sm" title="Previous (←)" aria-label="Previous file" @click="emit('step', -1)">
                            <VibeIcon icon="chevron-left" />
                        </VibeButton>
                        <div v-if="hoverSide === 'prev' && prevFile" class="ql-peek">
                            <img v-if="prevFile.thumb_url" :src="safeUrl(prevFile.thumb_url)" :alt="prevFile.name" loading="lazy">
                            <VibeIcon v-else :icon="iconFor(prevFile.type)" class="fs-2" />
                            <div class="text-truncate small mt-1">{{ prevFile.name }}</div>
                        </div>
                    </div>
                    <div class="position-relative" @mouseenter="hoverSide = 'next'" @mouseleave="hoverSide = null">
                        <VibeButton variant="light" size="sm" title="Next (→)" aria-label="Next file" @click="emit('step', 1)">
                            <VibeIcon icon="chevron-right" />
                        </VibeButton>
                        <div v-if="hoverSide === 'next' && nextFile" class="ql-peek">
                            <img v-if="nextFile.thumb_url" :src="safeUrl(nextFile.thumb_url)" :alt="nextFile.name" loading="lazy">
                            <VibeIcon v-else :icon="iconFor(nextFile.type)" class="fs-2" />
                            <div class="text-truncate small mt-1">{{ nextFile.name }}</div>
                        </div>
                    </div>
                    <VibeButton v-if="file?.id" variant="primary" size="sm" :href="`/download/${file.id}`">
                        <VibeIcon icon="download" class="me-1" />Download
                    </VibeButton>
                    <VibeDropdown
                        v-if="file"
                        variant="light"
                        size="sm"
                        menu-end
                        :items="menu"
                        @item-click="emit('action', $event)"
                    >
                        <template #button><VibeIcon icon="three-dots-vertical" class="me-1" />Actions</template>
                    </VibeDropdown>
                </div>
            </div>
        </template>

        <div v-if="file" class="quicklook-body d-flex flex-column align-items-center justify-content-center text-center" style="height: calc(100vh - 130px)">
            <VibeSpinner v-if="loading" class="mb-2" />
            <div v-if="loadError" class="alert alert-danger">{{ loadError }}</div>
            <img
                v-if="isImage(file)"
                :src="safeUrl(file.url)"
                :alt="file.name"
                class="img-fluid rounded"
                loading="lazy"
                style="max-height: 100%; object-fit: contain; aspect-ratio: 1"
                @load="loading = false"
                @error="loadError = 'Could not load image.'"
            >
            <iframe
                v-else-if="file.type === 'pdf'"
                :src="safeUrl(file.url)"
                :title="`PDF preview of ${file.name}`"
                class="w-100 h-100 border rounded"
                @load="loading = false"
                @error="loadError = 'Could not load preview.'"
            ></iframe>
            <audio v-else-if="file.mime?.startsWith('audio/')" :src="safeUrl(file.url)" controls class="w-100" />
            <video
                v-else-if="file.mime?.startsWith('video/')"
                :src="safeUrl(file.url)"
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
                :url="safeUrl(file.url)"
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
                <div class="small mt-2">{{ file.mime || 'unknown type' }} · {{ fmtBytes(file.size ?? 0) }}</div>
            </div>
        </div>
        <slot name="below" />
    </VibeModal>
</template>

<style>
/* VibeModal wraps the header slot in a content-sized .modal-title h5,
   teleports to <body> (out of scoped-CSS reach), and drops fallthrough
   attrs — so key on our own slot content: grow the wrapping title to the
   full header width so the nav/actions cluster can right-align. */
.modal-header > .modal-title:has(> .ql-header) {
    flex-grow: 1;
    min-width: 0;
}
</style>

<style scoped>
/* Hover preview of the adjacent file under the prev/next buttons. */
.ql-peek {
    position: absolute;
    top: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    width: 140px;
    padding: 6px;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.18);
    z-index: 1090;
    text-align: center;
    pointer-events: none;
}
.ql-peek img {
    width: 100%;
    height: 90px;
    object-fit: cover;
    border-radius: 0.35rem;
}
</style>
