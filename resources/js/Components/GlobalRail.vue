<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

interface NavItem {
    key: string;
    text: string;
    href: string;
    icon: string;
    active: boolean;
}

// Global app-nav rail: column 1 of the universal FourPane shell. Reproduces
// the nav items + active logic from AppLayout.vue (lines ~120-151) verbatim
// so every route shares one source of truth for "what are the destinations
// and which one is current."
const page = usePage();
const path = computed(() => page.url.split('?')[0]);
const query = computed(() => page.url.split('?')[1] || '');
const user = computed(() => (page.props.auth as { user?: { is_admin?: boolean } } | undefined)?.user);

function active(test: (p: string, q: string) => boolean): boolean {
    return test(path.value, query.value);
}

const mainNav = computed<NavItem[]>(() => [
    { key: 'home', text: 'Home', href: '/', icon: 'house-door-fill', active: active((p, q) => p === '/' && !q.includes('starred') && !q.includes('recent')) },
    { key: 'recent', text: 'Recent', href: '/?recent=1', icon: 'clock-history', active: active((p, q) => q.includes('recent=1')) },
    { key: 'starred', text: 'Starred', href: '/starred', icon: 'star-fill', active: active((p) => p.startsWith('/starred')) },
    { key: 'bookmarks', text: 'Bookmarks', href: '/bookmarks', icon: 'bookmark-fill', active: active((p) => p.startsWith('/bookmarks')) },
    { key: 'notes', text: 'Notes', href: '/notes', icon: 'journal-text', active: active((p) => p.startsWith('/notes')) },
    { key: 'vault', text: 'Vault', href: '/vault', icon: 'lock-fill', active: active((p) => p.startsWith('/vault')) },
    { key: 'photos', text: 'Photos', href: '/photos', icon: 'images', active: active((p) => p.startsWith('/photos')) },
    { key: 'shared', text: 'Shared with me', href: '/shared', icon: 'people-fill', active: active((p) => p.startsWith('/shared')) },
    { key: 'directory', text: 'Directory', href: '/directory', icon: 'person-rolodex', active: active((p) => p.startsWith('/directory')) },
    { key: 'trash', text: 'Trash', href: '/trash', icon: 'trash-fill', active: active((p) => p.startsWith('/trash')) },
]);

// Admin destinations are gated by page.props.auth.user.is_admin, mirroring
// AppLayout's adminNav guard exactly — do not widen this.
const adminNav = computed<NavItem[]>(() => (user.value?.is_admin ? [
    { key: 'users', text: 'Users', href: '/users', icon: 'person-gear', active: active((p) => p.startsWith('/users')) },
    { key: 'groups', text: 'Groups', href: '/groups', icon: 'diagram-3-fill', active: active((p) => p.startsWith('/groups')) },
    { key: 'audit', text: 'Audit', href: '/audit', icon: 'clipboard-check', active: active((p) => p.startsWith('/audit')) },
    { key: 'import', text: 'Import', href: '/import', icon: 'folder-symlink', active: active((p) => p.startsWith('/import')) },
    { key: 'backups', text: 'Backups', href: '/backups', icon: 'archive', active: active((p) => p.startsWith('/backups')) },
] : []));

</script>

<template>
    <nav class="global-rail d-flex flex-column align-items-center gap-1 px-2 py-2 h-100 w-100" aria-label="Main navigation">
        <Link
            v-for="item in mainNav"
            :key="item.key"
            :href="item.href"
            class="rail-link d-flex flex-column align-items-center justify-content-center rounded w-100 text-decoration-none"
            :class="{ active: item.active }"
            :data-nav="item.key"
            :aria-current="item.active ? 'page' : undefined"
            :title="item.text"
        >
            <VibeIcon :icon="item.icon" class="fs-5" />
            <span class="visually-hidden">{{ item.text }}</span>
        </Link>

        <template v-if="adminNav.length">
            <hr class="w-100 my-1">
            <Link
                v-for="item in adminNav"
                :key="item.key"
                :href="item.href"
                class="rail-link d-flex flex-column align-items-center justify-content-center rounded w-100 text-decoration-none"
                :class="{ active: item.active }"
                :data-nav="item.key"
                :aria-current="item.active ? 'page' : undefined"
                :title="item.text"
            >
                <VibeIcon :icon="item.icon" class="fs-5" />
                <span class="visually-hidden">{{ item.text }}</span>
            </Link>
        </template>

    </nav>
</template>

<style scoped>
.rail-link {
    color: var(--bs-secondary-color);
    padding: 0.4rem 0.15rem;
    cursor: pointer;
    line-height: 1.1;
}
.rail-link:hover {
    background: rgba(99, 102, 241, 0.08);
    color: var(--bs-body-color);
}
.rail-link.active {
    color: var(--bs-primary);
    background: rgba(99, 102, 241, 0.12);
    font-weight: 600;
}
</style>
