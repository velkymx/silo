<script setup lang="ts">
interface Section {
    key: string;
    icon: string;
    label: string;
}

defineProps<{
    sections: Section[];
    active: string;
}>();

const emit = defineEmits<{
    (e: 'select-section', key: string): void;
}>();
</script>

<template>
    <div class="section-rail d-flex flex-column align-items-center gap-1 py-2 h-100">
        <button
            v-for="s in sections"
            :key="s.key"
            type="button"
            class="rail-btn d-flex flex-column align-items-center justify-content-center border-0 bg-transparent rounded w-100"
            :class="{ active: active === s.key }"
            :data-section="s.key"
            :aria-label="s.label"
            :aria-current="active === s.key ? 'true' : undefined"
            :title="s.label"
            @click="emit('select-section', s.key)"
        >
            <VibeIcon :icon="s.icon" class="fs-5" />
            <span class="rail-label">{{ s.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.rail-btn {
    color: var(--bs-secondary-color);
    padding: 0.4rem 0.15rem;
    cursor: pointer;
    line-height: 1.1;
}
.rail-btn:hover {
    background: rgba(99, 102, 241, 0.08);
    color: var(--bs-body-color);
}
.rail-btn.active {
    color: var(--bs-primary);
    background: rgba(99, 102, 241, 0.12);
    font-weight: 600;
}
.rail-label {
    font-size: 0.625rem;
    margin-top: 0.15rem;
}
</style>
