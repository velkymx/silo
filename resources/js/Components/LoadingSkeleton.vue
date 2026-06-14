<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; cols?: number }>(), { rows: 6, cols: 4 });
</script>

<template>
    <div class="placeholder-glow" aria-busy="true" aria-live="polite">
        <span class="visually-hidden">Loading…</span>
        <div v-for="r in rows" :key="r" class="d-flex align-items-center gap-3 py-2 border-bottom">
            <span
                v-for="c in cols"
                :key="c"
                class="placeholder rounded"
                :class="c === 1 ? 'col-5' : 'col'"
            ></span>
        </div>
    </div>
</template>
