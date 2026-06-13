<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useColorMode } from '@velkymx/vibeui';

const mobileNavOpen = ref(false);

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash ?? {});

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
    { text: 'My Files', href: '/', icon: 'folder-fill', active: active((p, q) => p === '/' && !q.includes('starred') && !q.includes('recent')) },
    { text: 'Recent', href: '/?recent=1', icon: 'clock-history', active: active((p, q) => q.includes('recent=1')) },
    { text: 'Starred', href: '/?starred=1', icon: 'star-fill', active: active((p, q) => q.includes('starred=1')) },
    { text: 'Shared with me', href: '/shared', icon: 'people-fill', active: active((p) => p.startsWith('/shared')) },
    { text: 'Trash', href: '/trash', icon: 'trash-fill', active: active((p) => p.startsWith('/trash')) },
]);

const adminNav = computed(() => (user.value?.is_admin ? [
    { text: 'Users', href: '/users', icon: 'person-gear', active: active((p) => p.startsWith('/users')) },
    { text: 'Groups', href: '/groups', icon: 'diagram-3-fill', active: active((p) => p.startsWith('/groups')) },
    { text: 'Audit', href: '/audit', icon: 'clipboard-check', active: active((p) => p.startsWith('/audit')) },
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
            <VibeButton variant="secondary" size="sm" outline class="d-md-none" title="Menu" @click="mobileNavOpen = true">
                <VibeIcon icon="list" />
            </VibeButton>
            <a class="d-flex align-items-center text-decoration-none flex-shrink-0" style="cursor: pointer; width: 218px" @click="router.visit('/')">
                <VibeIcon icon="folder-fill" class="text-primary fs-4 me-2" />
                <span class="fw-bold fs-5 text-body d-none d-md-inline">File Manager</span>
            </a>
            <div class="flex-grow-1 min-vw-0">
                <slot name="topbar" />
            </div>
            <VibeButton variant="secondary" size="sm" outline :title="`Theme: ${colorMode}`" @click="toggleColorMode">
                <VibeIcon :icon="themeIcon" class="me-1" />{{ colorMode.charAt(0).toUpperCase() + colorMode.slice(1) }}
            </VibeButton>
            <VibeDropdown v-if="user" size="sm" variant="secondary" outline menu-end :items="userMenu" @item-click="onUserMenu">
                <template #button><VibeIcon icon="person-circle" class="me-1" />{{ user.name }}</template>
                <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
            </VibeDropdown>
        </header>

        <div class="d-flex flex-grow-1" style="min-height: 0">
            <!-- Sidebar -->
            <aside class="d-none d-md-flex flex-column flex-shrink-0 border-end bg-body p-3" style="width: 250px">
                <VibeNav pills vertical :items="baseNav" @item-click="onNav">
                    <template #item="{ item }">
                        <VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}
                    </template>
                </VibeNav>

                <template v-if="adminNav.length">
                    <hr class="my-3" >
                    <div class="text-muted text-uppercase small fw-semibold px-3 mb-1">Admin</div>
                    <VibeNav pills vertical :items="adminNav" @item-click="onNav">
                        <template #item="{ item }">
                            <VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}
                        </template>
                    </VibeNav>
                </template>

                <div class="pt-3 flex-grow-1 overflow-auto">
                    <slot name="sidebar" />
                </div>
            </aside>

            <!-- Content -->
            <main class="p-3 p-lg-4 flex-grow-1 min-vw-0">
                <VibeAlert v-if="flash.success" variant="success" dismissible>{{ flash.success }}</VibeAlert>
                <VibeAlert v-if="flash.error" variant="danger" dismissible>{{ flash.error }}</VibeAlert>
                <slot />
            </main>
        </div>

        <!-- Mobile navigation drawer -->
        <VibeOffcanvas v-model="mobileNavOpen" placement="start" title="File Manager">
            <VibeNav pills vertical :items="baseNav" @item-click="onNav">
                <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
            </VibeNav>
            <template v-if="adminNav.length">
                <hr class="my-3" >
                <div class="text-muted text-uppercase small fw-semibold px-3 mb-1">Admin</div>
                <VibeNav pills vertical :items="adminNav" @item-click="onNav">
                    <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                </VibeNav>
            </template>
        </VibeOffcanvas>
    </div>
</template>
