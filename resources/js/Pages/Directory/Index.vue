<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useUrlFilter } from '../../composables/useUrlFilter';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { http } from '../../lib/http';
import { initials } from '../../lib/initials';
import AppModal from '../../Components/AppModal.vue';

const props = defineProps({
    people: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Drive the server-side search the controller already supports — no giant
// client-side payload to filter.
const { filters: urlFilters, setFilter: setUrlFilter } = useUrlFilter({
    basePath: '/directory',
    initialFilters: { search: props.filters?.search ?? '', department: props.filters?.department ?? '' },
});
const search = computed({ get: () => urlFilters.value.search, set: (v) => setUrlFilter('search', v) });
const department = computed({ get: () => urlFilters.value.department, set: (v) => setUrlFilter('department', v) });

// Group the server-filtered people under their department heading.
const grouped = computed(() => {
    const map = new Map();
    for (const p of props.people) {
        const key = p.department || 'Unassigned';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(p);
    }
    return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]));
});

const showProfile = ref(false);
const profile = ref(null);
const loading = ref(false);

async function open(person) {
    showProfile.value = true;
    loading.value = true;
    profile.value = null;
    try {
        const data = await http.get(`/directory/${person.id}`);
        profile.value = data?.person ?? null;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="p-3 p-lg-4">
            <PageHeader title="Directory" icon="person-rolodex" />

            <div class="row g-2 mb-3">
                <div class="col-12 col-md-6">
                    <VibeFormInput v-model="search" type="search" placeholder="Search people, titles…" />
                </div>
                <div class="col-12 col-md-4">
                    <VibeFormSelect v-model="department">
                        <option value="">All departments</option>
                        <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                    </VibeFormSelect>
                </div>
            </div>

            <p v-if="!people.length" class="text-muted">No people found.</p>

            <div v-for="[dept, items] in grouped" :key="dept" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2">{{ dept }}</div>
                <div class="row g-3">
                    <div v-for="p in items" :key="p.id" class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <button type="button" class="card h-100 w-100 text-start border-0 shadow-sm person-card" @click="open(p)">
                            <div class="card-body d-flex align-items-center gap-3">
                                <img v-if="p.avatar_url" :src="p.avatar_url" alt="" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover">
                                <span v-else class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white"
                                      style="width: 44px; height: 44px">{{ initials(p.name) }}</span>
                                <div class="min-vw-0">
                                    <div class="fw-semibold text-truncate">{{ p.name }}</div>
                                    <div class="small text-muted text-truncate">{{ p.title || '—' }}</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <AppModal v-model="showProfile" :title="profile?.name || 'Profile'">
            <p v-if="loading" class="text-muted mb-0">Loading…</p>
            <div v-else-if="profile">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="" class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover">
                    <span v-else class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white"
                          style="width: 64px; height: 64px; font-size: 1.25rem">{{ initials(profile.name) }}</span>
                    <div>
                        <div class="h5 mb-0">{{ profile.name }}</div>
                        <div class="text-muted">{{ profile.title }}<span v-if="profile.department"> · {{ profile.department }}</span></div>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <template v-if="profile.email"><dt class="col-4 text-muted">Email</dt><dd class="col-8"><a :href="`mailto:${profile.email}`">{{ profile.email }}</a></dd></template>
                    <template v-if="profile.phone"><dt class="col-4 text-muted">Phone</dt><dd class="col-8">{{ profile.phone }}</dd></template>
                    <template v-if="profile.location"><dt class="col-4 text-muted">Location</dt><dd class="col-8">{{ profile.location }}</dd></template>
                    <template v-if="profile.manager"><dt class="col-4 text-muted">Reports to</dt><dd class="col-8">{{ profile.manager.name }}</dd></template>
                    <template v-if="profile.start_date"><dt class="col-4 text-muted">Started</dt><dd class="col-8">{{ profile.start_date }}</dd></template>
                    <template v-if="profile.bio"><dt class="col-12 text-muted mt-2">About</dt><dd class="col-12">{{ profile.bio }}</dd></template>
                </dl>
            </div>
        </AppModal>
    </AppLayout>
</template>

<style scoped>
.person-card {
    cursor: pointer;
    transition: box-shadow 0.15s;
}
.person-card:hover {
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1) !important;
}
</style>
