<script setup lang="ts">
import { router } from '@inertiajs/vue3';

interface QuickAction {
    key: string;
    text: string;
    icon: string;
    run: () => void;
}

// Frontend-only entry points into the existing create flows. Note creates in
// place (POST /notes redirects to the new note); the rest deep-link to their
// surface with ?new / ?upload, which auto-opens that surface's create UI.
const actions: QuickAction[] = [
    { key: 'upload', text: 'Upload File', icon: 'cloud-arrow-up', run: () => router.get('/', { upload: 1 }) },
    { key: 'note', text: 'New Note', icon: 'journal-plus', run: () => router.post('/notes', { name: 'Untitled' }) },
    { key: 'bookmark', text: 'Save Bookmark', icon: 'bookmark-plus', run: () => router.get('/bookmarks', { new: 1 }) },
    { key: 'secret', text: 'Add Secret', icon: 'shield-lock', run: () => router.get('/vault', { new: 1 }) },
];
</script>

<template>
    <div class="quick-actions d-flex flex-wrap gap-2" role="group" aria-label="Quick actions">
        <VibeButton
            v-for="a in actions"
            :key="a.key"
            variant="secondary"
            class="quick-actions__btn d-flex align-items-center gap-2"
            :data-action="a.key"
            @click="a.run"
        >
            <VibeIcon :icon="a.icon" />
            <span>{{ a.text }}</span>
        </VibeButton>
    </div>
</template>
