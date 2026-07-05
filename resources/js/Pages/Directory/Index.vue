<script setup>
import { ref, computed } from 'vue';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import FolderAccordion from '../../Components/FolderAccordion.vue';
import { useCategoryFolders } from '../../composables/useCategoryFolders';
import { useEscapeToClear } from '../../composables/useEscapeToClear';
import UserAvatar from '../../Components/UserAvatar.vue';
import { http } from '../../lib/http';
import EmptyState from '../../Components/EmptyState.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../composables/usePageLoading';

const props = defineProps({
    people: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { loading } = usePageLoading();

// ----- Shell panes -----
const activePane = ref('contents');

// ----- Departments as the folder pane -----
// The full people list ships on the unfiltered page; department filtering is
// client-side, matching the other shell surfaces.
const selectedDept = ref(props.filters?.department || null);
const deptNames = computed(() => {
    const fromPeople = new Set(props.people.map((p) => p.department || 'Unassigned'));
    return [...new Set([...props.departments, ...fromPeople])].sort();
});
const counts = computed(() => {
    const map = {};
    for (const p of props.people) {
        const key = p.department || 'Unassigned';
        map[key] = (map[key] ?? 0) + 1;
    }
    return map;
});

// Departments are flat strings; useCategoryFolders adapts them to
// FolderAccordion rows rooted at the "Directory" node.
const {
    folders: accordionFolders,
    counts: accordionCounts,
    selectedId: selectedDeptId,
    pickById: pickDeptById,
    openIds: accordionOpenIds,
} = useCategoryFolders({
    rootName: 'Directory',
    rootIcon: 'person-rolodex',
    names: deptNames,
    counts,
    total: computed(() => props.people.length),
    selected: selectedDept,
    onPick: (name) => {
        selectedDept.value = name;
        clearContentSelection();
        activePane.value = 'contents';
    },
});

const listed = computed(() => {
    if (selectedDept.value === null) return props.people;
    return props.people.filter((p) => (p.department || 'Unassigned') === selectedDept.value);
});

// ----- Explorer table -----
const tableColumns = [
    { key: 'name', label: 'Name', class: 'dir-col-name' },
    { key: 'title', label: 'Title', headerClass: 'd-none d-lg-table-cell', class: 'd-none d-lg-table-cell st-col-meta' },
    { key: 'department', label: 'Department', headerClass: 'd-none d-xl-table-cell', class: 'd-none d-xl-table-cell st-col-meta' },
];
const tableItems = computed(() => listed.value.map((p) => ({ ...p, department: p.department || 'Unassigned' })));
const listPage = ref(1);

// ----- Breadcrumb -----
const breadcrumbItems = computed(() => [
    { text: 'Directory', dept: null, href: '/directory', active: selectedDept.value === null },
    ...(selectedDept.value !== null
        ? [{ text: selectedDept.value, dept: selectedDept.value, href: '/directory', active: true }]
        : []),
]);
function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) pickDeptById(0);
}

// ----- Profile detail pane (race-guarded fetch, unchanged) -----
const selectedId = ref(null);
const profile = ref(null);
const profileLoading = ref(false);
const profileError = ref('');
let requestedId = 0;

async function open(person) {
    const id = person.id;
    requestedId = id;
    selectedId.value = id;
    activePane.value = 'detail';
    profileLoading.value = true;
    profileError.value = '';
    profile.value = null;
    try {
        const data = await http.get(`/directory/${id}`);
        if (requestedId === id) profile.value = data?.person ?? null;
    } catch {
        if (requestedId === id) profileError.value = 'Could not load profile. Please try again.';
    } finally {
        if (requestedId === id) profileLoading.value = false;
    }
}

function clearContentSelection() {
    selectedId.value = null;
    profile.value = null;
    profileError.value = '';
    if (activePane.value === 'detail') activePane.value = 'contents';
}

useEscapeToClear(clearContentSelection);
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="selectedId !== null">
        <template #viewNav>
            <FolderAccordion
                :folders="accordionFolders"
                :selected-id="selectedDeptId"
                :open-ids="accordionOpenIds"
                :counts="accordionCounts"
                :can-create="false"
                @select-folder="pickDeptById"
            />
        </template>

        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-1">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onBreadcrumb">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? 'person-rolodex' : 'people-fill'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
            </div>
        </template>

        <template #contents>
            <LoadingSkeleton v-if="loading" :rows="6" :cols="4" />

            <div v-else class="st-table-wrap overflow-auto flex-grow-1 px-2 pt-1" @click.self="clearContentSelection">
                <VibeDataTable
                    v-if="listed.length"
                    :items="tableItems"
                    :columns="tableColumns"
                    row-key="id"
                    hover
                    small
                    clickable
                    search-placeholder="Search people, titles…"
                    :show-per-page="false"
                    :per-page="50"
                    v-model:current-page="listPage"
                    sort-by="name"
                    class="dir-table"
                    @row-clicked="open"
                >
                    <template #cell(name)="{ item }">
                        <div class="d-flex align-items-center min-w-0">
                            <UserAvatar :user="item" :size="28" class="me-2 flex-shrink-0" />
                            <span class="text-truncate" :class="{ 'fw-semibold': item.id === selectedId }" :title="item.name">{{ item.name }}</span>
                        </div>
                    </template>
                    <template #cell(title)="{ item }">
                        <span class="text-muted small text-truncate d-inline-block dir-title" :title="item.title">{{ item.title || '—' }}</span>
                    </template>
                    <template #cell(department)="{ item }">
                        <span class="text-muted small text-nowrap">{{ item.department }}</span>
                    </template>
                </VibeDataTable>
                <EmptyState v-else icon="person-rolodex" title="No people found" hint="Try another department or search." />
            </div>
        </template>

        <template #detail>
            <div class="d-flex flex-column h-100 p-3 overflow-auto">
                <p v-if="profileLoading" class="text-muted mb-0"><VibeSpinner size="sm" class="me-2" />Loading…</p>
                <VibeAlert v-else-if="profileError" variant="danger" class="mb-0">{{ profileError }}</VibeAlert>
                <template v-else-if="profile">
                    <div class="d-flex align-items-center gap-3 mb-3 flex-shrink-0">
                        <UserAvatar :user="profile" :size="64" />
                        <div class="min-w-0">
                            <div class="h5 mb-0 text-break">{{ profile.name }}</div>
                            <div class="text-muted">{{ profile.title }}<span v-if="profile.department"> · {{ profile.department }}</span></div>
                        </div>
                    </div>
                    <dl class="row mb-0 small">
                        <template v-if="profile.email"><dt class="col-4 text-muted">Email</dt><dd class="col-8 text-break"><a :href="`mailto:${profile.email}`">{{ profile.email }}</a></dd></template>
                        <template v-if="profile.phone"><dt class="col-4 text-muted">Phone</dt><dd class="col-8">{{ profile.phone }}</dd></template>
                        <template v-if="profile.location"><dt class="col-4 text-muted">Location</dt><dd class="col-8">{{ profile.location }}</dd></template>
                        <template v-if="profile.manager"><dt class="col-4 text-muted">Reports to</dt><dd class="col-8">{{ profile.manager.name }}</dd></template>
                        <template v-if="profile.start_date"><dt class="col-4 text-muted">Started</dt><dd class="col-8">{{ profile.start_date }}</dd></template>
                        <template v-if="profile.bio"><dt class="col-12 text-muted mt-2">About</dt><dd class="col-12">{{ profile.bio }}</dd></template>
                    </dl>
                </template>
            </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.dir-title {
    max-width: 18rem;
    vertical-align: middle;
}
</style>
