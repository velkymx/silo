<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { iconFor } from '../../lib/fileTypes';

const props = defineProps({
    notes: { type: Array, default: () => [] },
    bookmarks: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
});

const empty = computed(() => !props.notes.length && !props.bookmarks.length && !props.files.length);
</script>

<template>
    <AppLayout>
        <div class="p-3 p-lg-4">
            <PageHeader title="Starred" icon="star-fill" />

            <p v-if="empty" class="text-muted">Nothing starred yet. Star notes, bookmarks, or files to pin them here.</p>

            <section v-if="notes.length" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2"><VibeIcon icon="journal-text" class="me-1" />Notes</div>
                <div class="list-group">
                    <button
                        v-for="n in notes"
                        :key="n.id"
                        type="button"
                        class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                        @click="router.visit(`/notes?open=${n.id}`)"
                    >
                        <VibeIcon icon="journal-text" class="text-muted" />
                        <span class="flex-grow-1 text-truncate">{{ n.title }}</span>
                        <span class="small text-muted">{{ n.updated_at }}</span>
                    </button>
                </div>
            </section>

            <section v-if="bookmarks.length" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2"><VibeIcon icon="bookmark-fill" class="me-1" />Bookmarks</div>
                <div class="list-group">
                    <a
                        v-for="b in bookmarks"
                        :key="b.id"
                        :href="`/bookmarks/${b.id}/go`"
                        target="_blank"
                        rel="noopener"
                        class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                    >
                        <VibeIcon icon="bookmark-fill" class="text-muted" />
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block text-truncate">{{ b.title }}</span>
                            <span class="d-block small text-muted text-truncate">{{ b.url }}</span>
                        </span>
                        <VibeIcon icon="box-arrow-up-right" class="text-muted" />
                    </a>
                </div>
            </section>

            <section v-if="files.length" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2"><VibeIcon icon="files" class="me-1" />Files</div>
                <div class="list-group">
                    <a
                        v-for="f in files"
                        :key="f.id"
                        :href="`/download/${f.id}`"
                        class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                    >
                        <VibeIcon :icon="iconFor(f.type)" class="text-muted" />
                        <span class="flex-grow-1 text-truncate">{{ f.name }}</span>
                        <VibeIcon icon="download" class="text-muted" />
                    </a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
