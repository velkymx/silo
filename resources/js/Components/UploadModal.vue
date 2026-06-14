<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ parentId: number | null; maxUploadKb: number }>();

/* eslint-disable @typescript-eslint/no-explicit-any */
type UFile = File & { __url?: string | null };

const uploadFiles = ref<UFile[]>([]);
const uploadForm = useForm<{ files: UFile[]; parent_id: number | null }>({ files: [], parent_id: props.parentId });
const uploadInput = ref<HTMLInputElement | null>(null);
const uploadDragOver = ref(false);

const maxUploadLabel = computed(() =>
    props.maxUploadKb >= 1024 ? `${(props.maxUploadKb / 1024).toFixed(0)} MB` : `${props.maxUploadKb} KB`,
);

function fmtBytes(n: number): string {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i ? 1 : 0)} ${u[i]}`;
}

function blobUrlFor(f: UFile): string | null {
    if (!f || typeof f.type !== 'string' || !f.type.startsWith('image/')) return null;
    const Url = (globalThis as any).URL;
    if (!Url?.createObjectURL) return null;
    if (f.__url) return f.__url;
    try {
        f.__url = Url.createObjectURL(f);
    } catch {
        return null;
    }
    return f.__url ?? null;
}

function revokeBlobUrl(f: UFile): void {
    if (f?.__url) {
        try { (globalThis as any).URL?.revokeObjectURL(f.__url); } catch { /* ignore */ }
        f.__url = null;
    }
}

watch(uploadFiles, (val) => { uploadForm.files = val; });

function addUploadFiles(list: FileList | File[]): void {
    const incoming = Array.from(list) as UFile[];
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
function isImageFile(f: UFile): boolean {
    return typeof f?.type === 'string' && f.type.startsWith('image/');
}
function clearAllUploads(): void {
    uploadFiles.value.forEach(revokeBlobUrl);
    uploadFiles.value = [];
}

function submitUpload(): void {
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

onBeforeUnmount(() => uploadFiles.value.forEach(revokeBlobUrl));
</script>

<template>
    <VibeModal v-model="open" title="Upload Files" fullscreen>
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
                    <VibeButton variant="link" size="sm" class="p-0 text-decoration-none" @click="clearAllUploads">Clear all</VibeButton>
                </div>
                <div class="border rounded" style="max-height: 40vh; overflow: auto">
                    <div v-for="(f, i) in uploadFiles" :key="i" class="d-flex align-items-center gap-2 p-2 border-bottom">
                        <img v-if="isImageFile(f) && blobUrlFor(f)" :src="f.__url!" class="rounded flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover">
                        <VibeIcon v-else icon="file-earmark" class="fs-4 text-secondary flex-shrink-0" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate small">{{ f.name }}</div>
                            <div class="text-muted" style="font-size: 0.72rem">{{ fmtBytes(f.size) }}</div>
                        </div>
                        <VibeButton variant="link" size="sm" class="p-0 text-danger" @click="removeUploadFile(i)"><VibeIcon icon="x-lg" /></VibeButton>
                    </div>
                </div>
            </div>

            <VibeProgress
                v-if="uploadForm.progress"
                :bars="[{ value: uploadForm.progress.percentage, showValue: true, variant: 'success' }]"
                class="mt-3"
            />
        </div>
        <template #footer>
            <div class="d-flex justify-content-center gap-2 w-100">
                <VibeButton variant="secondary" outline @click="open = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="uploadForm.processing || !uploadFiles.length" @click="submitUpload">
                    <VibeIcon icon="upload" class="me-1" />Upload{{ uploadFiles.length ? ` ${uploadFiles.length}` : '' }}
                </VibeButton>
            </div>
        </template>
    </VibeModal>
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
