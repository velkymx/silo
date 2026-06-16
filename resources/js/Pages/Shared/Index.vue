<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SharedListing from '../../Components/SharedListing.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import PageError from '../../Components/PageError.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { usePageLoading } from '../../composables/usePageLoading';

defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
});

const { loading } = usePageLoading();
</script>

<template>
    <AppLayout>
        <PageError />
        <h4 class="mb-3"><VibeIcon icon="people" class="me-2" />Shared with me</h4>
        <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
        <template v-else>
            <EmptyState
                v-if="!folders.length && !files.length"
                icon="people"
                title="Nothing shared with you yet"
                hint="Items others share will appear here."
            />
            <SharedListing v-else :folders="folders" :files="files" />
        </template>
    </AppLayout>
</template>
