<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    backups: { type: Array, default: () => [] },
    schedule: { type: Object, default: () => ({ frequency: 'off', retention: 7 }) },
    frequencies: { type: Array, default: () => ['off', 'daily', 'weekly', 'monthly'] },
});

const scheduleForm = useForm({
    frequency: props.schedule.frequency,
    retention: props.schedule.retention,
});

const frequencyOptions = computed(() =>
    props.frequencies.map((f) => ({ value: f, text: f.charAt(0).toUpperCase() + f.slice(1) }))
);

function saveSchedule() {
    scheduleForm.put('/backups/schedule', { preserveScroll: true });
}

function runNow() {
    router.post('/backups', {}, { preserveScroll: true });
}

function remove(b) {
    if (confirm(`Delete backup ${b.filename}? This cannot be undone.`)) {
        router.delete(`/backups/${b.id}`, { preserveScroll: true });
    }
}

function restore(b) {
    if (!confirm(`Restore ${b.filename}? This OVERWRITES all current data (database + files).`)) return;
    if (!confirm('Are you absolutely sure? This cannot be undone.')) return;
    router.post(`/backups/${b.id}/restore`, {}, { preserveScroll: true });
}

function human(bytes) {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(1)} ${units[i]}`;
}

const statusVariant = (s) => ({ ready: 'success', pending: 'info', failed: 'danger' }[s] || 'secondary');

const columns = [
    { key: 'filename', label: 'Backup' },
    { key: 'size', label: 'Size', formatter: human },
    { key: 'compression', label: 'Compression' },
    { key: 'status', label: 'Status' },
    { key: 'created_by', label: 'By' },
    { key: 'created_at', label: 'Created' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

// Auto-refresh while a backup is still being built.
let poll = null;
const hasPending = computed(() => props.backups.some((b) => b.status === 'pending'));
onMounted(() => {
    poll = setInterval(() => {
        if (hasPending.value) router.reload({ only: ['backups'] });
    }, 4000);
});
onBeforeUnmount(() => clearInterval(poll));
</script>

<template>
    <AppLayout>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="mb-0"><VibeIcon icon="archive" class="me-2" />Backups</h4>
            <VibeButton variant="primary" @click="runNow">
                <VibeIcon icon="play-fill" class="me-1" />Back up now
            </VibeButton>
        </div>

        <VibeRow class="g-3">
            <VibeCol :lg="4">
                <VibeCard header="Schedule">
                    <p class="text-muted small">
                        Automatic, compressed backups of the database and all stored files.
                        Older backups beyond the retention count are pruned.
                    </p>
                    <VibeFormGroup label="Frequency">
                        <VibeFormSelect v-model="scheduleForm.frequency" :options="frequencyOptions" />
                    </VibeFormGroup>
                    <VibeFormGroup label="Keep last (count)" class="mt-3">
                        <VibeFormInput v-model="scheduleForm.retention" type="number" min="1" max="365" />
                    </VibeFormGroup>
                    <VibeButton
                        variant="secondary"
                        class="mt-3"
                        :disabled="scheduleForm.processing"
                        @click="saveSchedule"
                    >
                        Save schedule
                    </VibeButton>
                    <VibeAlert variant="info" class="mt-3 mb-0 small">
                        Archives use <strong>ultra</strong> compression (bzip2 when available,
                        otherwise deflate). Requires the scheduler + a queue worker to be running.
                    </VibeAlert>
                </VibeCard>
            </VibeCol>

            <VibeCol :lg="8">
                <VibeCard header="Available backups">
                    <VibeDataTable
                        :items="backups"
                        :columns="columns"
                        row-key="id"
                        hover
                        :per-page="10"
                        empty-text="No backups yet. Run one or set a schedule."
                    >
                        <template #cell(filename)="{ item }">
                            <VibeIcon icon="file-earmark-zip" class="me-1 text-warning" />{{ item.filename }}
                        </template>
                        <template #cell(compression)="{ item }">
                            <VibeBadge v-if="item.compression === 'bzip2'" variant="primary">Ultra</VibeBadge>
                            <VibeBadge v-else-if="item.compression" variant="secondary">{{ item.compression }}</VibeBadge>
                            <span v-else>—</span>
                        </template>
                        <template #cell(status)="{ item }">
                            <VibeBadge :variant="statusVariant(item.status)">
                                <VibeSpinner v-if="item.status === 'pending'" size="sm" class="me-1" />{{ item.status }}
                            </VibeBadge>
                            <div v-if="item.status === 'failed'" class="small text-danger text-truncate" style="max-width: 220px" :title="item.note">
                                {{ item.note }}
                            </div>
                        </template>
                        <template #cell(actions)="{ item }">
                            <div class="d-flex justify-content-end gap-1">
                                <VibeButton
                                    v-if="item.status === 'ready'"
                                    variant="success"
                                    size="sm"
                                    :href="`/backups/${item.id}/download`"
                                    title="Download"
                                >
                                    <VibeIcon icon="download" />
                                </VibeButton>
                                <VibeButton
                                    v-if="item.status === 'ready'"
                                    variant="warning"
                                    size="sm"
                                    outline
                                    title="Restore (overwrites all data)"
                                    @click="restore(item)"
                                >
                                    <VibeIcon icon="arrow-counterclockwise" />
                                </VibeButton>
                                <VibeButton variant="danger" size="sm" outline title="Delete" @click="remove(item)">
                                    <VibeIcon icon="trash" />
                                </VibeButton>
                            </div>
                        </template>
                    </VibeDataTable>
                </VibeCard>
            </VibeCol>
        </VibeRow>
    </AppLayout>
</template>
