<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SharedListing from '../../Components/SharedListing.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../composables/usePageLoading';

defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
});

const { loading } = usePageLoading();
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="people" class="me-2" />Shared with me</h4>
        <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
        <template v-else>
            <p v-if="!folders.length && !files.length" class="text-muted">Nothing has been shared with you yet.</p>
            <SharedListing :folders="folders" :files="files" />
        </template>
    </AppLayout>
</template>
