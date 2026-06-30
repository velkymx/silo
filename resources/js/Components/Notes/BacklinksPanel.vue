<script setup>
import { ref, watch } from 'vue';
import { http } from '../../lib/http';

const props = defineProps({
    noteId: { type: Number, default: null },
});
const emit = defineEmits(['open']);

const backlinks = ref([]);
const loading = ref(false);
let loadSeq = 0;

watch(
    () => props.noteId,
    async (id) => {
        const seq = ++loadSeq;
        backlinks.value = [];
        if (!id) return;
        loading.value = true;
        try {
            const data = await http.get(`/notes/${id}/backlinks`);
            if (seq === loadSeq) backlinks.value = data?.backlinks ?? [];
        } catch {
            if (seq === loadSeq) backlinks.value = [];
        } finally {
            if (seq === loadSeq) loading.value = false;
        }
    },
    { immediate: true }
);
</script>

<template>
    <div v-if="noteId" class="backlinks border-top px-3 py-2">
        <div class="text-uppercase small text-muted fw-semibold mb-2">
            <VibeIcon icon="link-45deg" class="me-1" />Linked Mentions
        </div>
        <p v-if="loading" class="small text-muted mb-0">Loading…</p>
        <p v-else-if="!backlinks.length" class="small text-muted mb-0">No backlinks yet.</p>
        <ul v-else class="list-unstyled mb-0">
            <li v-for="link in backlinks" :key="link.id">
                <VibeButton
                    variant="link"
                    size="sm"
                    class="p-0 text-decoration-none"
                    @click="emit('open', link.id)"
                >
                    <VibeIcon icon="journal-text" class="me-1" />{{ link.title }}
                </VibeButton>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.backlinks {
    max-height: 200px;
    overflow-y: auto;
}
</style>
