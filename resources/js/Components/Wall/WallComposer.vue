<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{ wallUserId?: number | null }>();

const body = ref('');
const posting = ref(false);

const empty = computed(() => body.value.replace(/<[^>]*>/g, '').trim() === '');

function post(): void {
    if (empty.value || posting.value) return;
    router.post('/wall', { body: body.value, wall_user_id: props.wallUserId ?? null }, {
        preserveScroll: true,
        onStart: () => { posting.value = true; },
        onSuccess: () => { body.value = ''; },
        onFinish: () => { posting.value = false; },
    });
}
</script>

<template>
    <div class="wall-composer">
        <VibeFormWysiwyg v-model="body" placeholder="Write on the wall…" />
        <div class="d-flex justify-content-end mt-1">
            <VibeButton size="sm" variant="primary" :disabled="empty || posting" data-testid="wall-post-btn" @click="post">
                <VibeSpinner v-if="posting" size="sm" class="me-1" />Post
            </VibeButton>
        </div>
    </div>
</template>

<style scoped>
/* Quill defaults to a shallow strip; give the wall composer real room. */
.wall-composer :deep(.ql-editor) {
    min-height: 120px;
}
.wall-composer :deep(.ql-container) {
    border-bottom-left-radius: var(--bs-border-radius);
    border-bottom-right-radius: var(--bs-border-radius);
}
</style>
