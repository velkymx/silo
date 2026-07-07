<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
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

// `href` makes each crumb a real link (VibeBreadcrumb only wires clicks on
// items with an href); we intercept the click for Inertia navigation.
const crumbs = computed(() => [
    { text: 'Shared with me', folder: null, href: '/shared' },
    ...props.trail.map((t) => ({ text: t.name, folder: t.id, href: `/shared/${t.id}` })),
    { text: props.current.name, folder: props.current.id, active: true },
]);

function onCrumb({ item, event }) {
    event?.preventDefault?.();
    if (item.active) return;
    router.get(item.folder ? `/shared/${item.folder}` : '/shared', {}, { preserveScroll: true });
}
</script>

<template>
    <ShellLayout :folders-visible="false" :detail-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 px-3 py-2">
                <VibeBreadcrumb :items="crumbs" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onCrumb">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? 'people-fill' : 'folder2'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1 p-3">
                <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
                <SharedListing v-else :folders="folders" :files="files" />
            </div>
        </template>
    </ShellLayout>
</template>
