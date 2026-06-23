<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useConfirm } from '../../composables/useConfirm';

const props = defineProps({
    bookmarks: { type: Array, default: () => [] },
});

const { confirm } = useConfirm();

// Group bookmarks under their category heading (null → "General").
const grouped = computed(() => {
    const map = new Map();
    for (const b of props.bookmarks) {
        const key = b.category || 'General';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(b);
    }
    return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]));
});

const showModal = ref(false);
const editingId = ref(null);
const form = useForm({ title: '', url: '', description: '', icon: '', color: '', category: '', shared: false });

function openAdd() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(b) {
    editingId.value = b.id;
    form.clearErrors();
    Object.assign(form, {
        title: b.title, url: b.url, description: b.description || '',
        icon: b.icon === 'link-45deg' ? '' : b.icon || '', color: b.color || '',
        category: b.category || '', shared: b.shared,
    });
    showModal.value = true;
}

function save() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };
    if (editingId.value) form.put(`/bookmarks/${editingId.value}`, opts);
    else form.post('/bookmarks', opts);
}

async function remove(b) {
    if (await confirm({ title: 'Remove bookmark', message: `Remove “${b.title}”?`, confirmLabel: 'Remove', variant: 'danger' })) {
        router.delete(`/bookmarks/${b.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <div class="p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h4 mb-0"><VibeIcon icon="rocket-takeoff-fill" class="text-primary me-2" />Launchpad</h1>
                <VibeButton variant="primary" @click="openAdd"><VibeIcon icon="plus-lg" class="me-1" />Add bookmark</VibeButton>
            </div>

            <p v-if="!bookmarks.length" class="text-muted">No bookmarks yet. Add your first internal link.</p>

            <div v-for="[category, items] in grouped" :key="category" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2">{{ category }}</div>
                <div class="row g-3">
                    <div v-for="b in items" :key="b.id" class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <div class="card h-100 bookmark-card">
                            <div class="card-body d-flex align-items-start gap-3">
                                <span class="bookmark-icon d-inline-flex align-items-center justify-content-center rounded"
                                      :style="b.color ? { background: b.color + '22', color: b.color } : {}">
                                    <VibeIcon :icon="b.icon" class="fs-4" />
                                </span>
                                <div class="flex-grow-1 min-vw-0">
                                    <a :href="`/bookmarks/${b.id}/go`" target="_blank" rel="noopener"
                                       class="stretched-link text-decoration-none fw-semibold text-body d-block text-truncate">
                                        {{ b.title }}
                                    </a>
                                    <div class="small text-muted text-truncate">{{ b.description || b.url }}</div>
                                    <div class="mt-1 d-flex align-items-center gap-2">
                                        <VibeBadge v-if="b.shared" class="text-bg-light"><VibeIcon icon="people-fill" class="me-1" />Shared</VibeBadge>
                                        <span class="small text-muted">{{ b.click_count }} opens</span>
                                    </div>
                                </div>
                                <div v-if="b.can_edit" class="bookmark-actions d-flex flex-column gap-1">
                                    <VibeIcon icon="pencil" role="button" title="Edit" class="text-muted action" @click.stop.prevent="openEdit(b)" />
                                    <VibeIcon icon="trash" role="button" title="Remove" class="text-muted action" @click.stop.prevent="remove(b)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <VibeModal v-model="showModal" :title="editingId ? 'Edit bookmark' : 'Add bookmark'">
            <VibeFormGroup label="Title" :error="form.errors.title">
                <VibeFormInput v-model="form.title" placeholder="Payroll portal" />
            </VibeFormGroup>
            <VibeFormGroup label="URL" :error="form.errors.url">
                <VibeFormInput v-model="form.url" placeholder="https://…" />
            </VibeFormGroup>
            <VibeFormGroup label="Description" :error="form.errors.description">
                <VibeFormInput v-model="form.description" placeholder="Optional" />
            </VibeFormGroup>
            <div class="row">
                <div class="col-6">
                    <VibeFormGroup label="Category" :error="form.errors.category">
                        <VibeFormInput v-model="form.category" placeholder="Tools" />
                    </VibeFormGroup>
                </div>
                <div class="col-6">
                    <VibeFormGroup label="Icon" :error="form.errors.icon">
                        <VibeFormInput v-model="form.icon" placeholder="link-45deg" />
                    </VibeFormGroup>
                </div>
            </div>
            <VibeFormCheckbox v-model="form.shared">Share with everyone</VibeFormCheckbox>
            <template #footer>
                <VibeButton variant="secondary" outline @click="showModal = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="form.processing" @click="save">
                    {{ editingId ? 'Save' : 'Add' }}
                </VibeButton>
            </template>
        </VibeModal>
    </AppLayout>
</template>

<style scoped>
.bookmark-icon {
    width: 2.6rem;
    height: 2.6rem;
    background: rgba(99, 102, 241, 0.1);
    color: var(--bs-primary);
    flex-shrink: 0;
}
.bookmark-card:hover {
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
}
.bookmark-actions {
    position: relative;
    z-index: 2;
}
.bookmark-actions .action {
    cursor: pointer;
}
.bookmark-actions .action:hover {
    color: var(--bs-primary) !important;
}
</style>
