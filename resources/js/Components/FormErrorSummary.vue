<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ errors: Record<string, string> }>();
const emit = defineEmits<{ focus: [key: string] }>();

const entries = computed(() => Object.entries(props.errors).filter(([, v]) => v));
</script>

<template>
    <div v-if="entries.length" role="alert" aria-live="polite" class="alert alert-danger mb-3">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">
            <li v-for="[key, msg] in entries" :key="key">
                <a href="#" class="alert-link" @click.prevent="emit('focus', key)">{{ msg }}</a>
            </li>
        </ul>
    </div>
</template>
