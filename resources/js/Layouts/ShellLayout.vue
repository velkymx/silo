<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useColorMode } from '@velkymx/vibeui';
import { useCommandPalette } from '../composables/useCommandPalette';
import { useToast } from '../composables/useToast';
import { useStorageMeter } from '../composables/useStorageMeter';
import { fmtBytes } from '../lib/format';
import ToastHost from '../Components/ToastHost.vue';
import DialogHost from '../Components/DialogHost.vue';
import CommandPalette from '../Components/CommandPalette.vue';
import UserAvatar from '../Components/UserAvatar.vue';
import NotificationBell from '../Components/NotificationBell.vue';
import FourPane from '../Components/FourPane.vue';
import GlobalRail from '../Components/GlobalRail.vue';

// Universal shell: every route mounts this layout. It owns the app-wide
// chrome (top navbar, toasts, command palette, skip-link) plus the FourPane
// frame with GlobalRail fixed in column 1.
const { toggle: togglePalette } = useCommandPalette();
const toast = useToast();
const { colorMode, toggleColorMode } = useColorMode();
const themeIcon = computed(() => ({ light: 'sun-fill', dark: 'moon-stars-fill' }[colorMode.value] ?? 'circle-half'));
const page = usePage();
const user = computed(() => (page.props.auth as { user?: { name: string; avatar_url?: string | null } } | undefined)?.user);

// Brand + global file search.
const appName = import.meta.env.VITE_APP_NAME || 'Silo';
const tagline = 'Your Files Ready to Launch';
const year = new Date().getFullYear();
const repoUrl = 'https://github.com/velkymx/laravel-file-manager';
const searchValue = ref('');
const searchScope = ref<'all' | 'folder'>('all');
const currentFolder = computed(() => (page.props.currentFolder as { id: number } | null) ?? null);
const scopeMenu = [
    { text: 'All folders', value: 'all', icon: 'collection' },
    { text: 'This folder', value: 'folder', icon: 'folder2' },
];
function syncSearchFromUrl(): void {
    const q = new URLSearchParams(page.url.split('?')[1] || '');
    // The global search submits to /search?q=… (SearchController) — read the
    // same param back so the box reflects the active query on the results page.
    searchValue.value = q.get('q') || '';
    searchScope.value = q.get('scope') === 'folder' ? 'folder' : 'all';
}
syncSearchFromUrl();
watch(() => page.url, syncSearchFromUrl);
function runGlobalSearch(): void {
    const v = searchValue.value.trim();
    if (!v) { router.get('/'); return; }
    router.get('/search', { q: v });
}
function clearGlobalSearch(): void {
    searchValue.value = '';
    router.get('/');
}
const isSearching = computed(() => searchValue.value.length > 0);

// Passthrough model: pages that drive mobile pane-advance (e.g. Files moving
// `contents` -> `detail` on row select) bind their own `activePane` ref.
const activePane = defineModel<string>('activePane', { default: 'contents' });

// Passthrough: pages collapse the detail column when nothing is selected and
// drop the folders column on folder-less sections.
const props = withDefaults(
    defineProps<{ detailVisible?: boolean; foldersVisible?: boolean }>(),
    { detailVisible: true, foldersVisible: true },
);

// Shared storage meter, shown in the user menu (was the old sidebar footer).
const storage = computed(() => (page.props.storage as { used: number; quota: number } | null) ?? null);
const { pct: storagePct, bars: storageBars } = useStorageMeter(computed(() => storage.value ?? { used: 0, quota: 0 }));
const notifications = computed(() => (page.props.notifications as { unread_count: number; recent: Array<{ id: number; type: string; severity: string; title: string; url: string | null; read_at: string | null; created_at: string | null }> } | undefined) ?? { unread_count: 0, recent: [] });

const userMenu = computed(() => [
    { heading: 'Break Room' },
    { text: 'Crush', action: 'crush', icon: 'joystick' },
    { text: 'Word', action: 'word', icon: 'type' },
    { text: 'Sodoku', action: 'sodoku', icon: 'grid-3x3' },
    { divider: true },
    { text: 'Trash', action: 'trash', icon: 'trash' },
    { type: 'storage' },
    { text: 'Manage storage', action: 'storage', icon: 'hdd-stack' },
    { divider: true },
    { text: `Theme: ${colorMode.value.charAt(0).toUpperCase() + colorMode.value.slice(1)}`, action: 'theme', icon: themeIcon.value },
    { text: 'Profile', action: 'profile', icon: 'person' },
    { text: 'Logout', action: 'logout', icon: 'box-arrow-right' },
]);
const routeFor: Record<string, string> = {
    crush: '/break/crush', word: '/break/dwg', sodoku: '/break/sodoku',
    trash: '/trash', storage: '/usage', profile: '/profile',
};
function onUserMenu({ item }: { item: { action?: string } }): void {
    if (!item.action) return;
    if (item.action === 'theme') { toggleColorMode(); return; }
    if (item.action === 'logout') { router.post('/logout'); return; }
    if (routeFor[item.action]) router.visit(routeFor[item.action]);
}

// Cmd/Ctrl-K toggles the command palette from any page.
function onKeydown(e: KeyboardEvent): void {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        const t = e.target as HTMLElement;
        if (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable) return;
        e.preventDefault();
        togglePalette();
    }
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="shell-layout d-flex flex-column vh-100">
        <a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 z-3 p-2 bg-primary text-white text-decoration-none rounded-bottom-end">Skip to main content</a>

        <!-- Full-width sticky top bar: brand, centered global search, theme + user. -->
        <header class="shell-navbar sticky-top d-flex flex-wrap align-items-center gap-3 border-bottom bg-body px-3 py-2 flex-shrink-0">
            <Link href="/" class="d-flex align-items-center text-decoration-none flex-shrink-0" :title="`${appName} — ${tagline}`">
                <VibeIcon icon="rocket-takeoff-fill" class="text-primary fs-4 me-2" />
                <span class="d-none d-md-flex flex-column lh-1">
                    <span class="fw-bold fs-5 text-body">{{ appName }}</span>
                    <span class="text-muted" style="font-size: 0.62rem; letter-spacing: 0.02em">{{ tagline }}</span>
                </span>
            </Link>

            <div class="flex-grow-1 min-w-0">
                <VibeInputGroup class="mx-auto" style="max-width: 620px">
                    <template #prepend>
                        <span class="input-group-text bg-body border-end-0"><VibeIcon icon="search" class="text-muted" /></span>
                    </template>
                    <VibeFormInput
                        id="global-search"
                        v-model="searchValue"
                        type="search"
                        class="border-start-0"
                        placeholder="Search files, folders, tags…"
                        aria-label="Search files, folders, and tags"
                        no-wrapper
                        @keyup.enter="runGlobalSearch"
                    />
                    <template #append>
                        <VibeDropdown
                            variant="secondary"
                            menu-end
                            :items="scopeMenu"
                            :title="searchScope === 'folder' ? 'Scope: this folder' : 'Scope: all folders'"
                            @item-click="searchScope = $event.item.value; runGlobalSearch()"
                        >
                            <template #button>
                                <VibeIcon :icon="searchScope === 'folder' ? 'folder2' : 'collection'" class="me-1" />
                                {{ searchScope === 'folder' ? 'This folder' : 'All' }}
                            </template>
                            <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                        </VibeDropdown>
                        <VibeButton v-if="isSearching" variant="secondary" outline aria-label="Clear search" @click="clearGlobalSearch">
                            <VibeIcon icon="x-lg" />
                        </VibeButton>
                        <button v-else type="button" class="input-group-text bg-body text-muted border-0" title="Command palette" @click="togglePalette">
                            <kbd class="bg-body-secondary text-body-secondary border" style="font-size: 0.7rem">⌘K</kbd>
                        </button>
                    </template>
                </VibeInputGroup>
            </div>

            <NotificationBell v-if="user" :notifications="notifications" />

            <VibeDropdown v-if="user" size="sm" variant="secondary" class="rounded-pill" menu-end :items="userMenu" @item-click="onUserMenu">
                <template #button>
                    <UserAvatar :user="user" :size="22" class="me-2" />
                    <span class="d-none d-sm-inline">{{ user.name }}</span>
                </template>
                <template #item="{ item }">
                    <span v-if="item.heading" class="dropdown-header text-uppercase small fw-semibold">{{ item.heading }}</span>
                    <div v-else-if="item.type === 'storage'" class="px-3 py-2" style="min-width: 220px" @click.stop>
                        <VibeProgress v-if="storage && storage.quota > 0" :bars="storageBars" class="mb-1" style="height: 6px" />
                        <div class="small text-muted">
                            {{ fmtBytes(storage?.used || 0) }}<template v-if="storage && storage.quota > 0"> of {{ fmtBytes(storage.quota) }} ({{ storagePct }}%)</template><template v-else> used · unlimited</template>
                        </div>
                    </div>
                    <span v-else><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</span>
                </template>
            </VibeDropdown>
        </header>

        <!-- FourPane fills the rest below the navbar. -->
        <div class="flex-grow-1 min-h-0 p-0">
            <FourPane v-model:activePane="activePane" :detail-visible="props.detailVisible" :folders-visible="props.foldersVisible">
                <template #rail>
                    <GlobalRail />
                </template>
                <template #folders>
                    <slot name="viewNav" />
                </template>
                <template v-if="$slots.topBar" #topBar>
                    <slot name="topBar" />
                </template>
                <template #contents>
                    <div id="main-content" class="flex-grow-1 d-flex flex-column min-h-0">
                        <slot name="contents" />
                    </div>
                </template>
                <template #detail>
                    <slot name="detail" />
                </template>
            </FourPane>
        </div>

        <footer class="shell-footer border-top bg-body px-3 py-2 text-muted small d-flex flex-wrap align-items-center gap-1 flex-shrink-0">
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

        <ToastHost
            :items="toast.state.items"
            @dismiss="toast.dismiss($event)"
            @undo="(t) => { t.undo?.(); toast.dismiss(t.id); }"
        />

        <!-- Single in-app confirm/prompt host (replaces native window.confirm/prompt). -->
        <DialogHost />

        <CommandPalette />
    </div>
</template>

<style scoped>
.min-h-0 {
    min-height: 0;
}
</style>
