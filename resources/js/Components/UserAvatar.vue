<script setup lang="ts">
import { computed } from 'vue';
import { initials } from '../lib/initials';

interface UserLike { name: string; avatar_url?: string | null }
const props = withDefaults(defineProps<{ user: UserLike; size?: number }>(), { size: 36 });

const initial = computed(() => initials(props.user.name) || '?');
const fontPx = computed(() => Math.max(10, Math.round(props.size * 0.42)));
</script>

<template>
    <img
        v-if="user.avatar_url"
        :src="user.avatar_url"
        :alt="user.name"
        class="rounded-circle"
        :style="{ width: size + 'px', height: size + 'px', objectFit: 'cover' }"
    >
    <span
        v-else
        class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white"
        :style="{ width: size + 'px', height: size + 'px', fontSize: fontPx + 'px' }"
    >{{ initial }}</span>
</template>
