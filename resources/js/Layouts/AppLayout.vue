<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash ?? {});

const navItems = computed(() => {
    const items = [];
    if (user.value) items.push({ text: 'My Files', href: '/' });
    if (user.value) items.push({ text: 'Shared with me', href: '/shared' });
    if (user.value) items.push({ text: 'Trash', href: '/trash' });
    if (user.value?.is_admin) items.push({ text: 'Users', href: '/users' });
    if (user.value) items.push({ text: user.value.name, href: '/profile' });
    return items;
});

function onNav({ item, event }) {
    event.preventDefault();
    router.visit(item.href);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-vh-100 bg-body-tertiary">
        <VibeNavbar variant="dark" expand="md" class="shadow-sm mb-4">
            <VibeNavbarBrand href="/">
                <VibeIcon icon="folder-fill" class="me-1" />File Manager
            </VibeNavbarBrand>
            <VibeNavbarToggle target="mainNav" />
            <VibeCollapse id="mainNav" is-nav>
                <VibeNavbarNav class="ms-auto align-items-md-center" :items="navItems" @item-click="onNav" />
                <VibeButton v-if="user" variant="light" size="sm" outline class="ms-md-3" @click="logout">
                    <VibeIcon icon="box-arrow-right" class="me-1" />Logout
                </VibeButton>
            </VibeCollapse>
        </VibeNavbar>

        <VibeContainer>
            <VibeAlert v-if="flash.success" variant="success" dismissible>{{ flash.success }}</VibeAlert>
            <VibeAlert v-if="flash.error" variant="danger" dismissible>{{ flash.error }}</VibeAlert>

            <slot />
        </VibeContainer>
    </div>
</template>
