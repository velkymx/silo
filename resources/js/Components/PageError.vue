<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const dismissed = ref(false);

const messages = computed<string[]>(() => {
    const flash = (page.props.flash ?? {}) as { error?: string };
    const errors = (page.props.errors ?? {}) as Record<string, string>;
    const list: string[] = [];
    if (flash.error) list.push(flash.error);
    list.push(...Object.values(errors).filter(Boolean));
    return list;
});

// A fresh error (after a failed action) re-shows the alert even if dismissed.
watch(messages, () => { dismissed.value = false; });

const show = computed(() => messages.value.length > 0 && !dismissed.value);
</script>

<template>
    <VibeAlert v-if="show" variant="danger" dismissible class="mb-3" @close="dismissed = true">
        <div v-if="messages.length === 1">{{ messages[0] }}</div>
        <ul v-else class="mb-0 ps-3">
            <li v-for="m in messages" :key="m">{{ m }}</li>
        </ul>
    </VibeAlert>
</template>
