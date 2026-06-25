<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick, useId } from 'vue';

const props = defineProps<{
    label?: string;
    helpText?: string;
    error?: string | null | undefined;
}>();

const uid = useId();
const helpId = computed(() => props.helpText ? `${uid}-help` : null);
const errorId = computed(() => props.error ? `${uid}-error` : null);
const describedBy = computed(() =>
    [helpId.value, errorId.value].filter(Boolean).join(' ') || undefined,
);

const wrapper = ref<HTMLElement | null>(null);

function wireInput() {
    const el = wrapper.value?.querySelector<HTMLElement>(
        'input:not([type="hidden"]), select, textarea',
    );
    if (!el) return;
    el.id = el.id || uid;
    if (describedBy.value) el.setAttribute('aria-describedby', describedBy.value);
    else el.removeAttribute('aria-describedby');
}

onMounted(async () => { await nextTick(); wireInput(); });
watch(describedBy, () => wireInput());
</script>

<template>
    <div ref="wrapper" class="mb-3">
        <label v-if="label" :for="uid" class="form-label">{{ label }}</label>
        <slot />
        <small v-if="helpText" :id="helpId ?? undefined" class="form-text text-muted d-block mt-1">{{ helpText }}</small>
        <small v-if="error" :id="errorId ?? undefined" class="invalid-feedback d-block" role="alert" aria-live="polite">{{ error }}</small>
    </div>
</template>
