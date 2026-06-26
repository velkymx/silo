<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { fmtBytes } from '../lib/format';
import AppModal from './AppModal.vue';

const open = defineModel<boolean>({ required: true });
const props = withDefaults(
    defineProps<{ parentId: number | null; maxUploadKb: number; storage?: { used: number; quota: number } }>(),
    { storage: () => ({ used: 0, quota: 0 }) },
);

/* eslint-disable @typescript-eslint/no-explicit-any */

// Blob preview URLs are keyed off the File itself (a WeakMap-style Map) instead
// of mutating the native File with a `__url` property.
const blobUrls = new Map<File, string>();

const uploadFiles = ref<File[]>([]);
const uploadForm = useForm<{ files: File[]; parent_id: number | null }>({ files: [], parent_id: props.parentId });
const uploadInput = ref<HTMLInputElement | null>(null);
const uploadDragOver = ref(false);

const maxUploadLabel = computed(() =>
    props.maxUploadKb >= 1024 ? `${(props.maxUploadKb / 1024).toFixed(0)} MB` : `${props.maxUploadKb} KB`,
);

function blobUrlFor(f: File): string | null {
    if (!f || typeof f.type !== 'string' || !f.type.startsWith('image/')) return null;
    const Url = (globalThis as any).URL;
    if (!Url?.createObjectURL) return null;
    const existing = blobUrls.get(f);
    if (existing) return existing;
    try {
        const url = Url.createObjectURL(f);
        blobUrls.set(f, url);
        return url;
    } catch {
        return null;
    }
}

function revokeBlobUrl(f: File): void {
    const url = blobUrls.get(f);
    if (url) {
        try { (globalThis as any).URL?.revokeObjectURL(url); } catch { /* ignore */ }
        blobUrls.delete(f);
    }
}

watch(uploadFiles, (val) => { uploadForm.files = val; });

function addUploadFiles(list: FileList | File[]): void {
    const incoming = Array.from(list);
    for (const f of incoming) blobUrlFor(f);
    uploadFiles.value = [...uploadFiles.value, ...incoming];
}
function onUploadDrop(e: DragEvent): void {
    uploadDragOver.value = false;
    if (e.dataTransfer?.files?.length) addUploadFiles(e.dataTransfer.files);
}
function onUploadPick(e: Event): void {
    const input = e.target as HTMLInputElement;
    if (input.files?.length) addUploadFiles(input.files);
    input.value = '';
}
function removeUploadFile(i: number): void {
    revokeBlobUrl(uploadFiles.value[i]);
    uploadFiles.value = uploadFiles.value.filter((_, idx) => idx !== i);
}
function clearAllUploads(): void {
    uploadFiles.value.forEach(revokeBlobUrl);
    uploadFiles.value = [];
}

function submitUpload(): void {
    if (overQuota.value) return;
    uploadForm.parent_id = props.parentId;
    uploadForm.post('/upload', {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            uploadFiles.value.forEach(revokeBlobUrl);
            uploadFiles.value = [];
            open.value = false;
        },
    });
}

const totalBytes = computed(() => uploadFiles.value.reduce((s, f) => s + f.size, 0));

// Client-side quota pre-check: blocks the round-trip when the selection would
// blow the user's quota (the server still enforces it authoritatively).
const overQuota = computed(() =>
    props.storage.quota > 0 && props.storage.used + totalBytes.value > props.storage.quota,
);

onBeforeUnmount(() => uploadFiles.value.forEach(revokeBlobUrl));
</script>

<template>
    <AppModal v-model="open" title="Upload Files" size="lg" centered scrollable>
        <div class="mx-auto" style="max-width: 720px">
            <div
                class="upload-dropzone"
                :class="{ dragover: uploadDragOver }"
                @click="uploadInput?.click()"
                @dragover.prevent="uploadDragOver = true"
                @dragleave.prevent="uploadDragOver = false"
                @drop.prevent="onUploadDrop"
            >
                <VibeIcon icon="cloud-arrow-up" class="display-4 text-primary mb-2" />
                <div class="fw-semibold">Drag &amp; drop files here</div>
                <div class="text-muted small">or click to browse · up to {{ maxUploadLabel }} per file</div>
                <input ref="uploadInput" type="file" multiple class="d-none" @change="onUploadPick">
            </div>

            <p v-if="uploadForm.errors['files.0']" class="text-danger small mt-2">{{ uploadForm.errors['files.0'] }}</p>

            <div v-if="uploadFiles.length" class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-muted">{{ uploadFiles.length }} file(s) · {{ fmtBytes(totalBytes) }}</span>
                    <AppButton variant="link" size="sm" class="p-0 text-decoration-none" @click="clearAllUploads">Clear all</AppButton>
                </div>
                <div class="border rounded" style="max-height: 40vh; overflow: auto">
                    <div v-for="(f, i) in uploadFiles" :key="`${f.name}-${f.size}-${f.lastModified}`" class="d-flex align-items-center gap-2 p-2 border-bottom">
                        <img v-if="blobUrlFor(f)" :src="blobUrlFor(f)!" class="rounded flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover">
                        <VibeIcon v-else icon="file-earmark" class="fs-4 text-secondary flex-shrink-0" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate small">{{ f.name }}</div>
                            <div class="text-muted" style="font-size: 0.72rem">{{ fmtBytes(f.size) }}</div>
                        </div>
                        <AppButton variant="link" size="sm" class="p-0 text-danger" @click="removeUploadFile(i)"><VibeIcon icon="x-lg" /></AppButton>
                    </div>
                </div>
            </div>

            <VibeAlert v-if="overQuota" variant="danger" class="mt-3 mb-0">
                <VibeIcon icon="exclamation-triangle" class="me-1" />This selection ({{ fmtBytes(totalBytes) }}) would exceed your storage quota. Remove some files or free up space.
            </VibeAlert>

            <VibeProgress
                v-if="uploadForm.progress"
                :bars="[{ value: uploadForm.progress.percentage, showValue: true, variant: 'success' }]"
                class="mt-3"
            />
        </div>
        <template #footer>
            <div class="d-flex justify-content-center gap-2 w-100">
                <AppButton variant="secondary" outline @click="open = false">Cancel</AppButton>
                <AppButton variant="primary" :disabled="uploadForm.processing || !uploadFiles.length || overQuota" @click="submitUpload">
                    <VibeIcon icon="upload" class="me-1" />Upload{{ uploadFiles.length ? ` ${uploadFiles.length}` : '' }}
                </AppButton>
            </div>
        </template>
    </AppModal>
</template>

<style scoped>
.upload-dropzone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 0.75rem;
    padding: 2.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    background: var(--bs-body-tertiary);
}
.upload-dropzone:hover,
.upload-dropzone.dragover {
    border-color: var(--bs-primary);
    background: var(--bs-primary-bg-subtle);
}
.min-w-0 { min-width: 0; }
</style>
