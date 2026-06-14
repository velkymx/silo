<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useColorMode, useBreakpoints } from '@velkymx/vibeui';
import FolderTree from '../Components/FolderTree.vue';
import { useConfirm, useDialogHost } from '../composables/useConfirm';

const { confirm } = useConfirm();
const { state: dialog, accept: dialogAccept, cancel: dialogCancel } = useDialogHost();

const { isMobile } = useBreakpoints();
const mobileNavOpen = ref(false);
const sidebarOpen = ref(true);

function toggleSidebar() {
    if (isMobile.value) mobileNavOpen.value = true;
    else sidebarOpen.value = !sidebarOpen.value;
}

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash ?? {});

// Shared folder tree (consistent on every route).
const folders = computed(() => page.props.folders ?? []);
const currentFolder = computed(() => page.props.currentFolder ?? null);
const ancestorIds = computed(() => {
    const byId = new Map(folders.value.map((f) => [f.id, f]));
    const ids = new Set();
    let id = currentFolder.value;
    while (id && byId.has(id)) {
        ids.add(id);
        id = byId.get(id).parent_id;
    }
    return ids;
});
function goFolder(id) {
    router.get('/', id ? { folder: id } : {}, { preserveScroll: true });
}

// Global search (unified in the top bar, present on every page).
const searchValue = ref('');
const searchScope = ref('all'); // 'all' | 'folder'
function syncSearchFromUrl() {
    const q = new URLSearchParams(page.url.split('?')[1] || '');
    searchValue.value = q.get('search') || '';
    searchScope.value = q.get('scope') === 'folder' ? 'folder' : 'all';
}
syncSearchFromUrl();
watch(() => page.url, syncSearchFromUrl);
const scopeMenu = [
    { text: 'All folders', value: 'all', icon: 'collection' },
    { text: 'This folder', value: 'folder', icon: 'folder' },
];
function runGlobalSearch() {
    const v = searchValue.value.trim();
    if (!v) { router.get('/', {}); return; }
    const params = { search: v };
    if (searchScope.value === 'folder' && currentFolder.value) {
        params.scope = 'folder';
        params.folder = currentFolder.value;
    }
    router.get('/', params);
}
function clearGlobalSearch() {
    searchValue.value = '';
    router.get('/', {});
}
const isSearching = computed(() => searchValue.value.length > 0);

// Cmd/Ctrl-K focuses the global search from any page.
function onKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        document.getElementById('global-search')?.focus();
    }
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

// Smart folders (saved searches).
const savedSearches = computed(() => page.props.savedSearches ?? []);
function runSavedSearch(s) {
    router.get('/', s.params || {});
}
async function deleteSavedSearch(s) {
    if (await confirm({ title: 'Remove smart folder', message: `Remove smart folder "${s.name}"?`, confirmLabel: 'Remove', variant: 'danger' })) {
        router.delete(`/saved-searches/${s.id}`, { preserveScroll: true });
    }
}

// Shared storage meter (consistent on every page).
const storage = computed(() => page.props.storage ?? null);
const storagePct = computed(() =>
    storage.value?.quota > 0 ? Math.min(100, Math.round((storage.value.used / storage.value.quota) * 100)) : 0
);
const storageBars = computed(() => [{
    value: storagePct.value,
    variant: storagePct.value > 90 ? 'danger' : storagePct.value > 75 ? 'warning' : 'success',
}]);
function fmtBytes(n) {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i ? 1 : 0)} ${u[i]}`;
}

// Footer legalese.
const year = new Date().getFullYear();
const appName = 'File Manager by AJBApps';
const repoUrl = 'https://github.com/velkymx/laravel-file-manager';

const path = computed(() => page.url.split('?')[0]);
const query = computed(() => page.url.split('?')[1] || '');

const { colorMode, toggleColorMode } = useColorMode();
const themeIcon = computed(() => ({
    light: 'sun-fill',
    dark: 'moon-stars-fill',
    auto: 'circle-half',
}[colorMode.value] ?? 'circle-half'));

function active(test) {
    return test(path.value, query.value);
}

const baseNav = computed(() => [
    { text: 'Home', href: '/', icon: 'house-door-fill', active: active((p, q) => p === '/' && !q.includes('starred') && !q.includes('recent')) },
    { text: 'Recent', href: '/?recent=1', icon: 'clock-history', active: active((p, q) => q.includes('recent=1')) },
    { text: 'Starred', href: '/?starred=1', icon: 'star-fill', active: active((p, q) => q.includes('starred=1')) },
    { text: 'Photos', href: '/photos', icon: 'images', active: active((p) => p.startsWith('/photos')) },
    { text: 'Shared with me', href: '/shared', icon: 'people-fill', active: active((p) => p.startsWith('/shared')) },
]);

const trashNav = computed(() => [
    { text: 'Trash', href: '/trash', icon: 'trash-fill', active: active((p) => p.startsWith('/trash')) },
]);

const adminNav = computed(() => (user.value?.is_admin ? [
    { text: 'Users', href: '/users', icon: 'person-gear', active: active((p) => p.startsWith('/users')) },
    { text: 'Groups', href: '/groups', icon: 'diagram-3-fill', active: active((p) => p.startsWith('/groups')) },
    { text: 'Audit', href: '/audit', icon: 'clipboard-check', active: active((p) => p.startsWith('/audit')) },
    { text: 'Import', href: '/import', icon: 'folder-symlink', active: active((p) => p.startsWith('/import')) },
    { text: 'Backups', href: '/backups', icon: 'archive', active: active((p) => p.startsWith('/backups')) },
] : []));

const userMenu = [
    { text: 'Profile', href: '/profile', icon: 'person' },
    { divider: true },
    { text: 'Logout', action: 'logout', icon: 'box-arrow-right' },
];

function onNav({ item, event }) {
    event?.preventDefault?.();
    mobileNavOpen.value = false;
    router.visit(item.href);
}

function onUserMenu({ item }) {
    if (item.action === 'logout') router.post('/logout');
    else if (item.href) router.visit(item.href);
}
</script>

<template>
    <div class="d-flex flex-column min-vh-100 bg-body-tertiary">
        <!-- Full-width top bar -->
        <header class="d-flex align-items-center gap-3 border-bottom bg-body px-3 py-2">
            <VibeButton variant="secondary" size="sm" outline title="Toggle sidebar" @click="toggleSidebar">
                <VibeIcon icon="list" />
            </VibeButton>
            <a class="d-flex align-items-center text-decoration-none flex-shrink-0" style="cursor: pointer; width: 218px" @click="router.visit('/')">
                <VibeIcon icon="folder-fill" class="text-primary fs-4 me-2" />
                <span class="fw-bold fs-5 text-body d-none d-md-inline">File Manager</span>
            </a>
            <div class="flex-grow-1 min-vw-0">
                <VibeInputGroup class="mx-auto" style="max-width: 620px">
                    <template #prepend>
                        <span class="input-group-text bg-body border-end-0"><VibeIcon icon="search" class="text-muted" /></span>
                    </template>
                    <VibeFormInput
                        id="global-search"
                        v-model="searchValue"
                        type="search"
                        class="border-start-0"
                        :placeholder="searchScope === 'folder' ? 'Search this folder…' : 'Search files, folders, tags…'"
                        no-wrapper
                        @keyup.enter="runGlobalSearch"
                    />
                    <template #append>
                        <VibeDropdown
                            variant="light"
                            menu-end
                            :items="scopeMenu"
                            :title="searchScope === 'folder' ? 'Scope: this folder' : 'Scope: all folders'"
                            @item-click="searchScope = $event.item.value; runGlobalSearch()"
                        >
                            <template #button>
                                <VibeIcon :icon="searchScope === 'folder' ? 'folder' : 'collection'" class="me-1" />
                                {{ searchScope === 'folder' ? 'This folder' : 'All' }}
                            </template>
                            <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                        </VibeDropdown>
                        <VibeButton v-if="isSearching" variant="secondary" outline @click="clearGlobalSearch">
                            <VibeIcon icon="x-lg" />
                        </VibeButton>
                        <span v-else class="input-group-text bg-body text-muted">
                            <kbd class="bg-body-secondary text-body-secondary border" style="font-size: 0.7rem">⌘K</kbd>
                        </span>
                    </template>
                </VibeInputGroup>
            </div>
            <VibeButton variant="light" size="sm" class="rounded-pill px-3" :title="`Theme: ${colorMode}`" @click="toggleColorMode">
                <VibeIcon :icon="themeIcon" class="me-1" />{{ colorMode.charAt(0).toUpperCase() + colorMode.slice(1) }}
            </VibeButton>
            <VibeDropdown v-if="user" size="sm" variant="light" class="rounded-pill" menu-end :items="userMenu" @item-click="onUserMenu">
                <template #button>
                    <img
                        v-if="user.avatar_url"
                        :src="user.avatar_url"
                        alt=""
                        class="rounded-circle me-2"
                        style="width: 22px; height: 22px; object-fit: cover"
                    >
                    <span
                        v-else
                        class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white me-2"
                        style="width: 22px; height: 22px; font-size: 0.72rem"
                    >{{ user.name.charAt(0).toUpperCase() }}</span>{{ user.name }}
                </template>
                <template #item="{ item }"><VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}</template>
            </VibeDropdown>
        </header>

        <div class="d-flex flex-grow-1" style="min-height: 0">
            <!-- Sidebar (collapsible on desktop, offcanvas on mobile) -->
            <aside v-if="!isMobile && sidebarOpen" class="app-sidebar d-flex flex-column flex-shrink-0 border-end bg-body p-2" style="width: 250px">
                <VibeNav pills vertical :items="baseNav" @item-click="onNav">
                    <template #item="{ item }">
                        <VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}
                    </template>
                </VibeNav>

                <template v-if="adminNav.length">
                    <div class="side-heading"><VibeIcon icon="shield-lock-fill" />Admin</div>
                    <VibeNav pills vertical :items="adminNav" @item-click="onNav">
                        <template #item="{ item }">
                            <VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}
                        </template>
                    </VibeNav>
                </template>

                <div class="flex-grow-1 overflow-auto">
                    <div class="side-heading"><VibeIcon icon="folder-fill" />Folders</div>
                    <div
                        class="side-row d-flex align-items-center"
                        :class="{ active: !currentFolder }"
                        role="button"
                        @click="goFolder(null)"
                    >
                        <VibeIcon icon="house-door-fill" class="nav-ico" />Home
                    </div>
                    <FolderTree :folder="null" :current-id="currentFolder" :reveal-ids="ancestorIds" />

                    <template v-if="savedSearches.length">
                        <div class="side-heading"><VibeIcon icon="funnel-fill" />Smart Folders</div>
                        <div v-for="s in savedSearches" :key="s.id" class="side-row d-flex align-items-center saved-search" role="button" @click="runSavedSearch(s)">
                            <VibeIcon icon="bookmark-star-fill" class="nav-ico text-primary" />
                            <span class="text-truncate flex-grow-1">{{ s.name }}</span>
                            <VibeIcon icon="x" class="del-btn text-muted" title="Remove" @click.stop="deleteSavedSearch(s)" />
                        </div>
                    </template>

                    <slot name="sidebar" />
                </div>

                <!-- Trash pinned to the bottom, directly above the Storage block. -->
                <VibeNav pills vertical :items="trashNav" class="pt-2 mt-2 border-top" @item-click="onNav">
                    <template #item="{ item }">
                        <VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}
                    </template>
                </VibeNav>

                <!-- Storage meter, pinned to the bottom and consistent across pages. -->
                <div v-if="storage" class="pt-2 mt-2 border-top">
                    <div class="side-heading"><VibeIcon icon="hdd-fill" />Storage</div>
                    <div class="px-2">
                        <template v-if="storage.quota > 0">
                            <VibeProgress :bars="storageBars" class="mb-1" />
                            <div class="small text-muted">{{ fmtBytes(storage.used) }} of {{ fmtBytes(storage.quota) }} ({{ storagePct }}%)</div>
                        </template>
                        <div v-else class="small text-muted">{{ fmtBytes(storage.used) }} used · unlimited</div>
                        <a href="/usage" class="small text-decoration-none d-inline-block mt-1" @click.prevent="router.visit('/usage')">Manage storage</a>
                    </div>
                </div>
            </aside>

            <!-- Content -->
            <main class="p-3 p-lg-4 flex-grow-1 min-vw-0 bg-body d-flex flex-column">
                <VibeAlert v-if="flash.success" variant="success" dismissible>{{ flash.success }}</VibeAlert>
                <VibeAlert v-if="flash.error" variant="danger" dismissible>{{ flash.error }}</VibeAlert>
                <div class="flex-grow-1">
                    <slot />
                </div>

                <footer class="border-top mt-4 pt-3 text-muted small d-flex flex-wrap align-items-center gap-1">
                    <span>&copy; {{ year }} {{ appName }}</span>
                    <span>Released under the
                        <a :href="`${repoUrl}/blob/main/LICENSE`" target="_blank" rel="noopener" class="text-decoration-none">MIT License</a>.
                    </span>
                    <span class="ms-auto d-inline-flex align-items-center gap-2">
                        <a :href="repoUrl" target="_blank" rel="noopener" class="text-decoration-none">
                            <VibeIcon icon="github" class="me-1" />Source on GitHub
                        </a>
                    </span>
                </footer>
            </main>
        </div>

        <!-- Mobile navigation drawer -->
        <VibeOffcanvas v-model="mobileNavOpen" placement="start" title="File Manager">
            <VibeNav pills vertical :items="baseNav" @item-click="onNav">
                <template #item="{ item }"><VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}</template>
            </VibeNav>
            <template v-if="adminNav.length">
                <hr class="my-3" >
                <div class="text-muted text-uppercase small fw-semibold px-3 mb-1">Admin</div>
                <VibeNav pills vertical :items="adminNav" @item-click="onNav">
                    <template #item="{ item }"><VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}</template>
                </VibeNav>
            </template>
            <hr class="my-3" >
            <VibeNav pills vertical :items="trashNav" @item-click="onNav">
                <template #item="{ item }"><VibeIcon :icon="item.icon" class="nav-ico" />{{ item.text }}</template>
            </VibeNav>
        </VibeOffcanvas>

        <!-- Single in-app confirm/prompt host (replaces native window.confirm/prompt). -->
        <VibeModal v-model="dialog.open" :title="dialog.title" size="sm" centered @hide="dialogCancel">
            <p class="mb-0">{{ dialog.message }}</p>
            <VibeFormInput
                v-if="dialog.mode === 'prompt'"
                v-model="dialog.inputValue"
                :placeholder="dialog.placeholder"
                class="mt-3"
                autofocus
                @keyup.enter="dialogAccept"
            />
            <template #footer>
                <VibeButton variant="secondary" outline @click="dialogCancel">{{ dialog.cancelLabel }}</VibeButton>
                <VibeButton :variant="dialog.variant" @click="dialogAccept">{{ dialog.confirmLabel }}</VibeButton>
            </template>
        </VibeModal>
    </div>
</template>

<style scoped>
/* Even alignment of sidebar nav icons (Bootstrap glyphs vary in width). */
.nav-ico {
    display: inline-block;
    width: 1.25rem;
    margin-right: 0.6rem;
    text-align: center;
    flex-shrink: 0;
}

/* One consistent left edge + padding for every sidebar row and heading. */
.app-sidebar :deep(.nav-link),
.app-sidebar .side-row {
    padding: 0.4rem 0.6rem;
    border-radius: 0.5rem;
    cursor: pointer;
    color: var(--bs-body-color);
}
.app-sidebar .side-row:hover {
    background: rgba(99, 102, 241, 0.07);
}
.app-sidebar .side-row.active {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    font-weight: 600;
}
.app-sidebar .del-btn {
    opacity: 0;
    cursor: pointer;
}
.app-sidebar .saved-search:hover .del-btn {
    opacity: 1;
}
</style>
