<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirm } from '../composables/useConfirm';
import { useToast } from '../composables/useToast';
import { useBusyGuard } from '../composables/useBusyGuard';
import { useBatchRename } from '../composables/useBatchRename';

interface Item { id: number; name: string }
interface Option { value: number | null; text: string }

const props = defineProps<{
    selectedItems: Item[];
    currentId: number | null;
    destinationOptions: Option[];
}>();

const emit = defineEmits<{ done: []; cleared: [] }>();

const count = computed(() => props.selectedItems.length);
const batchIds = computed(() => props.selectedItems.map((i) => i.id));

const { confirm } = useConfirm();
const toast = useToast();
// Single-flight guard: only one batch op runs at a time.
const { busy: batchBusy, run: runBatch, release: releaseBatch } = useBusyGuard({ autoRelease: false });

function finishAndClose(close: () => void) {
    return {
        preserveScroll: true,
        onSuccess: () => { close(); emit('done'); },
        onFinish: releaseBatch,
    };
}

async function batchDelete(): Promise<void> {
    if (!await confirm({ title: 'Move to trash', message: `Move ${count.value} item(s) to trash?`, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    const ids = [...batchIds.value];
    runBatch(() => router.post('/files/batch/delete', { ids }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('done');
            toast.push(`${ids.length} item(s) moved to trash`, {
                undo: () => router.post('/trash/batch/restore', { ids }, { preserveScroll: true }),
            });
        },
        onFinish: releaseBatch,
    }));
}

// New folder from selection.
const folderOpen = ref(false);
const folderName = ref('New Folder');
function submitFolder(): void {
    runBatch(() => router.post('/files/batch/folder', { name: folderName.value, ids: batchIds.value, parent_id: props.currentId }, finishAndClose(() => { folderOpen.value = false; })));
}

// Batch move.
const moveOpen = ref(false);
const moveTarget = ref<number | null>(null);
function submitMove(): void {
    runBatch(() => router.post('/files/batch/move', { ids: batchIds.value, target_id: moveTarget.value || null }, finishAndClose(() => { moveOpen.value = false; })));
}

// Batch rename (find/replace, prefix/suffix, numbering).
const renameOpen = ref(false);
const { renameOpts, renamePreview } = useBatchRename(computed(() => props.selectedItems));
function submitRename(): void {
    const renames = renamePreview.value.map(({ id, to }) => ({ id, name: to }));
    runBatch(() => router.post('/files/batch/rename', { renames }, finishAndClose(() => { renameOpen.value = false; })));
}
</script>

<template>
    <div v-if="count" class="batch-bar d-flex flex-wrap align-items-center gap-2 border rounded bg-body p-2 mb-3 shadow-sm">
        <strong>{{ count }} selected</strong>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <!-- Move is the most common bulk action → the single primary. -->
            <VibeButton variant="primary" size="sm" :disabled="batchBusy" @click="moveTarget = null; moveOpen = true">
                <VibeIcon icon="folder-symlink" class="me-1" />Move…
            </VibeButton>
            <VibeButton variant="secondary" size="sm" outline :disabled="batchBusy" @click="folderName = 'New Folder'; folderOpen = true">
                <VibeIcon icon="folder-plus" class="me-1" />New Folder
            </VibeButton>
            <VibeButton variant="secondary" size="sm" outline :disabled="batchBusy" @click="renameOpen = true">
                <VibeIcon icon="input-cursor-text" class="me-1" />Rename…
            </VibeButton>
            <VibeButton variant="danger" size="sm" outline :disabled="batchBusy" @click="batchDelete">
                <VibeIcon icon="trash" class="me-1" />Delete
            </VibeButton>
            <VibeButton variant="secondary" size="sm" outline @click="emit('cleared')">Clear</VibeButton>
        </div>
    </div>

    <!-- New folder from selection -->
    <VibeModal v-model="folderOpen" title="New Folder from Selection" centered>
        <div class="mx-auto" style="max-width: 520px">
            <VibeFormGroup label="Folder name">
                <VibeFormInput v-model="folderName" />
            </VibeFormGroup>
            <p class="text-muted small mt-2">{{ count }} item(s) will be moved into it.</p>
        </div>
        <template #footer>
            <VibeButton variant="secondary" outline @click="folderOpen = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="!folderName || batchBusy" @click="submitFolder">Create</VibeButton>
        </template>
    </VibeModal>

    <!-- Batch move -->
    <VibeModal v-model="moveOpen" title="Move Selection To" centered>
        <div class="mx-auto" style="max-width: 520px">
            <VibeFormGroup label="Destination folder">
                <VibeFormSelect v-model="moveTarget" :options="destinationOptions" />
            </VibeFormGroup>
        </div>
        <template #footer>
            <VibeButton variant="secondary" outline @click="moveOpen = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="batchBusy" @click="submitMove">Move {{ count }}</VibeButton>
        </template>
    </VibeModal>

    <!-- Batch rename -->
    <VibeModal v-model="renameOpen" title="Batch Rename" size="lg" centered scrollable>
        <div class="mx-auto" style="max-width: 760px">
            <VibeButtonGroup class="mb-3">
                <VibeButton :variant="renameOpts.mode === 'replace' ? 'primary' : 'secondary'" :outline="renameOpts.mode !== 'replace'" @click="renameOpts.mode = 'replace'">Find &amp; Replace</VibeButton>
                <VibeButton :variant="renameOpts.mode === 'add' ? 'primary' : 'secondary'" :outline="renameOpts.mode !== 'add'" @click="renameOpts.mode = 'add'">Add Text</VibeButton>
                <VibeButton :variant="renameOpts.mode === 'number' ? 'primary' : 'secondary'" :outline="renameOpts.mode !== 'number'" @click="renameOpts.mode = 'number'">Numbering</VibeButton>
            </VibeButtonGroup>

            <div v-if="renameOpts.mode === 'replace'" class="row g-2">
                <div class="col"><VibeFormGroup label="Find"><VibeFormInput v-model="renameOpts.find" /></VibeFormGroup></div>
                <div class="col"><VibeFormGroup label="Replace with"><VibeFormInput v-model="renameOpts.replace" /></VibeFormGroup></div>
            </div>
            <div v-else-if="renameOpts.mode === 'add'" class="row g-2 align-items-end">
                <div class="col"><VibeFormGroup label="Text"><VibeFormInput v-model="renameOpts.text" /></VibeFormGroup></div>
                <div class="col-auto">
                    <VibeFormGroup label="Position">
                        <VibeFormSelect v-model="renameOpts.position" :options="[{ value: 'before', text: 'Before name' }, { value: 'after', text: 'After name' }]" />
                    </VibeFormGroup>
                </div>
            </div>
            <div v-else class="row g-2 align-items-end">
                <div class="col"><VibeFormGroup label="Base name"><VibeFormInput v-model="renameOpts.base" placeholder="(keep original)" /></VibeFormGroup></div>
                <div class="col-auto"><VibeFormGroup label="Start at"><VibeFormInput v-model.number="renameOpts.start" type="number" style="width: 90px" /></VibeFormGroup></div>
                <div class="col-auto"><VibeFormGroup label="Digits"><VibeFormInput v-model.number="renameOpts.pad" type="number" style="width: 80px" /></VibeFormGroup></div>
            </div>

            <h6 class="mt-3 text-muted">Preview</h6>
            <div class="border rounded" style="max-height: 50vh; overflow: auto">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr v-for="r in renamePreview" :key="r.id">
                            <td class="text-muted text-truncate" style="max-width: 280px">{{ r.from }}</td>
                            <td class="text-center text-muted"><VibeIcon icon="arrow-right" /></td>
                            <td class="fw-medium text-truncate" :class="{ 'text-danger': r.to !== r.from && renamePreview.filter(x => x.to === r.to).length > 1 }">{{ r.to }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <template #footer>
            <VibeButton variant="secondary" outline @click="renameOpen = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="batchBusy" @click="submitRename">Rename {{ count }}</VibeButton>
        </template>
    </VibeModal>
</template>

<style scoped>
/* Batch action bar sticks to the top while scrolling a long selection. */
.batch-bar {
    position: sticky;
    top: 0.5rem;
    z-index: 5;
}
</style>
