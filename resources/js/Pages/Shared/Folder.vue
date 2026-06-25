<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SharedListing from '../../Components/SharedListing.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../composables/usePageLoading';

const { loading } = usePageLoading();

const props = defineProps({
    current: { type: Object, required: true },
    trail: { type: Array, default: () => [] },
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
});

const crumbs = computed(() => [
    { text: 'Shared with me', folder: null },
    ...props.trail.map((t) => ({ text: t.name, folder: t.id })),
    { text: props.current.name, folder: props.current.id, active: true },
]);

function onCrumb({ item }) {
    if (item.active) return;
    router.get(item.folder ? `/shared/${item.folder}` : '/shared', {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <VibeBreadcrumb :items="crumbs" class="mb-3" @item-click="onCrumb" />
        <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
        <SharedListing v-else :folders="folders" :files="files" />
    </AppLayout>
</template>
