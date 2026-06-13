<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    root: { type: String, default: '' },
    fileCount: { type: Number, default: null },
});

const name = ref('Imported');
const scanning = ref(false);

function rescan() {
    scanning.value = true;
    router.post('/import/rescan', { name: name.value }, {
        preserveScroll: true,
        onFinish: () => { scanning.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="folder-symlink" class="me-2" />Import Folder</h4>

        <VibeRow class="justify-content-center">
            <VibeCol :lg="8">
                <VibeCard header="Index a mounted server folder">
                    <p class="text-muted">
                        Files in the import folder are indexed <strong>in place</strong> — they are
                        referenced, never copied or deleted. Re-scanning is safe to repeat; new files
                        are picked up and existing ones are not duplicated.
                    </p>

                    <dl class="row mb-3 small">
                        <dt class="col-sm-3 text-muted">Source path</dt>
                        <dd class="col-sm-9"><code>{{ root || '—' }}</code></dd>
                        <dt class="col-sm-3 text-muted">Files found</dt>
                        <dd class="col-sm-9">
                            <span v-if="fileCount === null" class="text-muted">Folder not mounted / empty.</span>
                            <VibeBadge v-else variant="secondary">{{ fileCount }}</VibeBadge>
                        </dd>
                    </dl>

                    <VibeFormGroup label="Top-level folder name">
                        <VibeFormInput v-model="name" placeholder="Imported" />
                    </VibeFormGroup>

                    <VibeButton variant="primary" class="mt-3" :disabled="scanning" @click="rescan">
                        <VibeSpinner v-if="scanning" size="sm" class="me-1" />
                        <VibeIcon v-else icon="arrow-repeat" class="me-1" />Re-scan now
                    </VibeButton>

                    <VibeAlert variant="info" class="mt-3 mb-0 small">
                        The scan runs in the background (queue worker required). Large folders take a
                        while; files appear in your tree as they are indexed.
                    </VibeAlert>
                </VibeCard>
            </VibeCol>
        </VibeRow>
    </AppLayout>
</template>
