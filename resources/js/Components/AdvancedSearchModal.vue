<script setup lang="ts">
import { computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAdvancedSearch, type AdvFilters } from '../composables/useAdvancedSearch';
import { usePrompt } from '../composables/useConfirm';

const { prompt } = usePrompt();

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ filters: AdvFilters; allTags: { id: number; name: string }[] }>();

const { adv, typeOptions, openAdvanced, advParams } = useAdvancedSearch(computed(() => props.filters));
const tagFilterOptions = computed(() => [
    { value: '', text: 'Any tag' },
    ...props.allTags.map((t) => ({ value: t.id, text: t.name })),
]);

// Seed the form from the current filters each time the modal opens.
watch(open, (v) => {
    if (v) openAdvanced();
});

function applyAdvanced(): void {
    router.get('/', advParams(), { preserveScroll: true });
    open.value = false;
}

async function saveSmartFolder(): Promise<void> {
    const name = await prompt({ title: 'Save smart folder', message: 'Name this smart folder:', placeholder: 'My Smart Folder', confirmLabel: 'Save' });
    if (!name) return;
    router.post('/saved-searches', { name, params: advParams() }, {
        preserveScroll: true,
        onSuccess: () => { open.value = false; },
    });
}
</script>

<template>
    <VibeModal v-model="open" title="Advanced Search" fullscreen>
        <div class="mx-auto" style="max-width: 640px">
            <VibeFormGroup label="Contains text">
                <VibeFormInput v-model="adv.search" placeholder="Name or content…" />
            </VibeFormGroup>
            <div class="row g-2 mt-1">
                <div class="col"><VibeFormGroup label="Date from"><VibeFormInput v-model="adv.date_from" type="date" /></VibeFormGroup></div>
                <div class="col"><VibeFormGroup label="Date to"><VibeFormInput v-model="adv.date_to" type="date" /></VibeFormGroup></div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col"><VibeFormGroup label="Min size (MB)"><VibeFormInput v-model="adv.size_min" type="number" min="0" step="0.1" /></VibeFormGroup></div>
                <div class="col"><VibeFormGroup label="Max size (MB)"><VibeFormInput v-model="adv.size_max" type="number" min="0" step="0.1" /></VibeFormGroup></div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col"><VibeFormGroup label="Type"><VibeFormSelect v-model="adv.ftype" :options="typeOptions" /></VibeFormGroup></div>
                <div class="col"><VibeFormGroup label="Tag"><VibeFormSelect v-model="adv.tag" :options="tagFilterOptions" /></VibeFormGroup></div>
            </div>
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
