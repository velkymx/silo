<script setup lang="ts">
import { computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    useAdvancedSearch,
    DATE_TARGET_OPTIONS,
    SIZE_UNIT_OPTIONS,
    type AdvFilters,
} from '../composables/useAdvancedSearch';
import { usePrompt } from '../composables/useConfirm';

const { prompt } = usePrompt();

const open = defineModel<boolean>({ required: true });
const props = defineProps<{
    filters: AdvFilters;
    allTags: { id: number; name: string }[];
    currentFolderId?: number | null;
    currentFolderName?: string | null;
}>();

const { adv, typeOptions, openAdvanced, advParams } = useAdvancedSearch(computed(() => props.filters));
const tagFilterOptions = computed(() => [
    { value: '', text: 'Any tag' },
    ...props.allTags.map((t) => ({ value: t.id, text: t.name })),
]);
const dateTargetOptions = DATE_TARGET_OPTIONS;
const sizeUnitOptions = SIZE_UNIT_OPTIONS;
const canScopeFolder = computed(() => props.currentFolderId != null);
const scopeLabel = computed(() =>
    props.currentFolderName ? `Only “${props.currentFolderName}” and its subfolders` : 'This folder and its subfolders',
);
const scopeFolder = computed({
    get: () => adv.value.scope === 'folder',
    set: (v: boolean) => { adv.value.scope = v ? 'folder' : 'all'; },
});

// Seed the form from the current filters each time the modal opens.
watch(open, (v) => {
    if (v) openAdvanced();
});

/** Wire params plus the current folder id when scoping to this folder. */
function params(): Record<string, string | number> {
    const p = advParams();
    if (p.scope === 'folder' && props.currentFolderId != null) p.folder = props.currentFolderId;
    return p;
}

function applyAdvanced(): void {
    router.get('/', params(), { preserveScroll: true });
    open.value = false;
}

async function saveSmartFolder(): Promise<void> {
    // Capture the form first, then close this modal BEFORE prompting: two
    // open VibeModals inert each other (and share a z-index), leaving the
    // prompt unclickable. Cancelling reopens the search form.
    const savedParams = params();
    open.value = false;
    const name = await prompt({ title: 'Save smart folder', message: 'Name this smart folder:', placeholder: 'My Smart Folder', confirmLabel: 'Save' });
    if (!name) {
        open.value = true;
        return;
    }
    router.post('/saved-searches', { name, params: savedParams }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <VibeModal v-model="open" title="Advanced Search" size="lg" centered scrollable>
        <div class="mx-auto" style="max-width: 640px">
            <VibeFormGroup label="Contains text">
                <VibeFormInput v-model="adv.search" placeholder="Name or content…" />
            </VibeFormGroup>

            <VibeFormGroup label="Date" class="mt-1">
                <div class="row g-2">
                    <div class="col-12 col-sm-4"><VibeFormSelect v-model="adv.date_target" :options="dateTargetOptions" /></div>
                    <div class="col"><VibeFormInput v-model="adv.date_from" type="date" aria-label="Date from" /></div>
                    <div class="col"><VibeFormInput v-model="adv.date_to" type="date" aria-label="Date to" /></div>
                </div>
            </VibeFormGroup>

            <VibeFormGroup label="Size" class="mt-1">
                <div class="row g-2">
                    <div class="col"><VibeFormInput v-model="adv.size_min" type="number" min="0" step="any" placeholder="Min" aria-label="Min size" /></div>
                    <div class="col"><VibeFormInput v-model="adv.size_max" type="number" min="0" step="any" placeholder="Max" aria-label="Max size" /></div>
                    <div class="col-4 col-sm-3"><VibeFormSelect v-model="adv.size_unit" :options="sizeUnitOptions" aria-label="Size unit" /></div>
                </div>
            </VibeFormGroup>

            <div class="row g-2 mt-1">
                <div class="col"><VibeFormGroup label="Type"><VibeFormSelect v-model="adv.ftype" :options="typeOptions" /></VibeFormGroup></div>
                <div class="col"><VibeFormGroup label="Tag"><VibeFormSelect v-model="adv.tag" :options="tagFilterOptions" /></VibeFormGroup></div>
            </div>

            <VibeFormGroup v-if="canScopeFolder" label="Where" class="mt-1">
                <VibeFormSwitch v-model="scopeFolder" :label="scopeLabel" />
            </VibeFormGroup>
        </div>
        <template #footer>
            <div class="d-flex w-100 gap-2">
                <VibeButton variant="secondary" outline @click="saveSmartFolder">
                    <VibeIcon icon="bookmark-plus" class="me-1" />Save as Smart Folder
                </VibeButton>
                <div class="ms-auto d-flex gap-2">
                    <VibeButton variant="secondary" outline @click="open = false">Cancel</VibeButton>
                    <VibeButton variant="primary" @click="applyAdvanced"><VibeIcon icon="search" class="me-1" />Search</VibeButton>
                </div>
            </div>
        </template>
    </VibeModal>
</template>
