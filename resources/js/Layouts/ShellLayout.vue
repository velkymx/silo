<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useColorMode } from '@velkymx/vibeui';
import { useCommandPalette } from '../composables/useCommandPalette';
import { useToast } from '../composables/useToast';
import { useStorageMeter } from '../composables/useStorageMeter';
import { fmtBytes } from '../lib/format';
import ToastHost from '../Components/ToastHost.vue';
import CommandPalette from '../Components/CommandPalette.vue';
import UserAvatar from '../Components/UserAvatar.vue';
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

// Passthrough model: pages that drive mobile pane-advance (e.g. Files moving
// `contents` -> `detail` on row select) bind their own `activePane` ref.
const activePane = defineModel<string>('activePane', { default: 'contents' });

// Shared storage meter, shown in the user menu (was the old sidebar footer).
const storage = computed(() => (page.props.storage as { used: number; quota: number } | null) ?? null);
const { pct: storagePct, bars: storageBars } = useStorageMeter(computed(() => storage.value ?? { used: 0, quota: 0 }));

const userMenu = [
    { heading: 'Break Room' },
    { text: 'Crush', action: 'crush', icon: 'joystick' },
    { text: 'Word', action: 'word', icon: 'type' },
    { text: 'Sodoku', action: 'sodoku', icon: 'grid-3x3' },
    { divider: true },
    { text: 'Trash', action: 'trash', icon: 'trash' },
    { type: 'storage' },
    { text: 'Manage storage', action: 'storage', icon: 'hdd-stack' },
    { divider: true },
    { text: 'Profile', action: 'profile', icon: 'person' },
    { text: 'Logout', action: 'logout', icon: 'box-arrow-right' },
];
const routeFor: Record<string, string> = {
    crush: '/break/crush', word: '/break/dwg', sodoku: '/break/sodoku',
    trash: '/trash', storage: '/usage', profile: '/profile',
};
function onUserMenu({ item }: { item: { action?: string } }): void {
    if (!item.action) return;
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

        <!-- Top navbar: brand, global command search, user menu. Same on every route. -->
        <nav class="shell-navbar d-flex align-items-center gap-2 px-3 flex-shrink-0 border-bottom bg-body">
            <Link href="/" class="navbar-brand d-flex align-items-center gap-2 fw-semibold text-body text-decoration-none mb-0">
                <VibeIcon icon="box-seam-fill" class="text-primary" />
                <span class="d-none d-sm-inline">Silo</span>
            </Link>

            <button
                type="button"
                class="shell-search btn btn-sm text-start text-muted d-flex align-items-center gap-2 me-auto ms-2"
                @click="togglePalette"
            >
                <VibeIcon icon="search" />
                <span class="d-none d-md-inline">Search everything…</span>
                <kbd class="ms-auto d-none d-lg-inline">⌘K</kbd>
            </button>

            <VibeButton size="sm" variant="secondary" outline class="rounded-pill" :title="`Theme: ${colorMode}`" aria-label="Toggle theme" @click="toggleColorMode">
                <VibeIcon :icon="themeIcon" />
            </VibeButton>

            <VibeDropdown v-if="user" size="sm" variant="secondary" outline class="rounded-pill" menu-end :items="userMenu" @item-click="onUserMenu">
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
        </nav>

        <!-- FourPane fills the rest below the navbar. -->
        <div class="flex-grow-1 min-h-0 p-0">
            <FourPane v-model:activePane="activePane">
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

        <ToastHost
            :items="toast.state.items"
            @dismiss="toast.dismiss($event)"
            @undo="(t) => { t.undo?.(); toast.dismiss(t.id); }"
        />

        <CommandPalette />
    </div>
</template>

<style scoped>
.shell-navbar {
    height: 52px;
}
.shell-search {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 0.5rem;
    width: min(360px, 40vw);
}
.shell-search:hover {
    background: var(--bs-secondary-bg);
}
.min-h-0 {
    min-height: 0;
}
</style>
