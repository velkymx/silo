<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SharedListing from '../../Components/SharedListing.vue';

defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="people" class="me-2" />Shared with me</h4>
        <p v-if="!folders.length && !files.length" class="text-muted">Nothing has been shared with you yet.</p>
        <SharedListing :folders="folders" :files="files" />
    </AppLayout>
</template>
