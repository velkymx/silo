<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { fmtBytes } from '../lib/format';
import { useFileUpload } from '../composables/useFileUpload';

const open = defineModel({ type: Boolean, required: true });
const props = defineProps({
    parentId: { type: Number, default: null },
    maxUploadKb: { type: Number, required: true },
    storage: { type: Object, default: () => ({ used: 0, quota: 0 }) },
});

const uploadInput = ref(null);
const uploadDragOver = ref(false);

const {
    items,
    totalBytes,
    activeCount,
    doneCount,
    errorCount,
    enqueue,
    cancel,
    retry,
    remove,
    clear,
    blobUrlFor,
} = useFileUpload({ url: '/upload', parentId: props.parentId, maxRetries: 2, autoStart: true });

const maxUploadLabel = computed(() =>
    props.maxUploadKb >= 1024 ? `${(props.maxUploadKb / 1024).toFixed(0)} MB` : `${props.maxUploadKb} KB`,
);

function onUploadDrop(e) {
    uploadDragOver.value = false;
    if (e.dataTransfer?.files?.length) enqueue(e.dataTransfer.files);
}
function onUploadPick(e) {
    const input = e.target;
    if (input.files?.length) enqueue(input.files);
    input.value = '';
}

const overQuota = computed(() =>
    props.storage.quota > 0 && props.storage.used + totalBytes.value > props.storage.quota,
);

// Auto-close once every item has settled and at least one succeeded.
const allSettled = computed(() => items.value.length > 0
    && items.value.every(i => i.state === 'done' || i.state === 'error' || i.state === 'cancelled'));
let closeTimer = null;
function maybeCloseOnAllDone() {
    if (allSettled.value && doneCount.value > 0) {
        if (closeTimer) return;
        closeTimer = setTimeout(() => {
            if (open.value) {
                clear();
                open.value = false;
                // Soft refresh of the files list so the new uploads appear without a full reload.
                router.reload({ only: ['files', 'storage'] });
            }
            closeTimer = null;
        }, 600);
    }
}
</script>

<template>
    <VibeModal v-model="open" title="Upload Files" size="lg" centered scrollable>
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

            <div v-if="items.length" class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-muted">
                        {{ doneCount }}/{{ items.length }} uploaded
                        <span v-if="errorCount" class="text-danger ms-2">· {{ errorCount }} failed</span>
                        · {{ fmtBytes(totalBytes) }}
                    </span>
                    <VibeButton variant="link" size="sm" class="p-0 text-decoration-none" :disabled="activeCount > 0" @click="clear">Clear all</VibeButton>
                </div>
                <div class="border rounded" style="max-height: 40vh; overflow: auto">
                    <div v-for="it in items" :key="it.id" class="d-flex align-items-center gap-2 p-2 border-bottom upload-row">
                        <img v-if="blobUrlFor(it.file)" :src="blobUrlFor(it.file)" class="rounded flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover">
                        <VibeIcon v-else icon="file-earmark" class="fs-4 text-secondary flex-shrink-0" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate small">{{ it.file.name }}</div>
                            <div class="d-flex align-items-center gap-2">
                                <VibeProgress
                                    v-if="it.state === 'uploading' || it.state === 'done'"
                                    :bars="[{ value: it.progress, showValue: true, variant: it.state === 'done' ? 'success' : 'primary' }]"
                                    class="flex-grow-1"
                                />
                                <span v-else-if="it.state === 'error'" class="text-danger small text-truncate" :title="it.error">{{ it.error }}</span>
                                <span v-else-if="it.state === 'cancelled'" class="text-muted small">Cancelled</span>
                                <span v-else class="text-muted small">Pending</span>
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <VibeButton
                                v-if="it.state === 'uploading' || it.state === 'pending'"
                                variant="link" size="sm" class="p-1 text-muted"
                                :aria-label="`Cancel ${it.file.name}`"
                                @click="cancel(it.id)"
                            ><VibeIcon icon="x-circle" /></VibeButton>
                            <VibeButton
                                v-if="it.state === 'error' && it.retries < 2"
                                variant="link" size="sm" class="p-1 text-primary"
                                :aria-label="`Retry ${it.file.name}`"
                                @click="retry(it.id)"
                            ><VibeIcon icon="arrow-clockwise" /></VibeButton>
                            <VibeButton
                                v-if="it.state !== 'uploading'"
                                variant="link" size="sm" class="p-1 text-danger"
                                :aria-label="`Remove ${it.file.name}`"
                                @click="remove(it.id)"
                            ><VibeIcon icon="x-lg" /></VibeButton>
                        </div>
                    </div>
                </div>
            </div>

            <VibeAlert v-if="overQuota" variant="danger" class="mt-3 mb-0">
                <VibeIcon icon="exclamation-triangle" class="me-1" />This selection ({{ fmtBytes(totalBytes) }}) would exceed your storage quota. Remove some files or free up space.
            </VibeAlert>
        </div>
        <template #footer>
            <div class="d-flex justify-content-center gap-2 w-100">
                <VibeButton variant="secondary" outline @click="open = false">Close</VibeButton>
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
.upload-row { gap: 0.5rem; }
</style>
