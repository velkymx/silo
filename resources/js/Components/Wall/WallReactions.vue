<script setup lang="ts">
import { router } from '@inertiajs/vue3';

export interface WallReaction {
    icon: string;
    count: number;
    mine: boolean;
}

const props = defineProps<{ postId: number; reactions: WallReaction[] }>();

// Mirrors WallController::REACTION_ICONS.
const PICKER_ICONS = [
    'hand-thumbs-up', 'heart-fill', 'emoji-laughing', 'emoji-surprised',
    'emoji-frown', 'fire', 'rocket-takeoff', 'star-fill',
];

function toggle(icon: string): void {
    router.post(`/wall/${props.postId}/react`, { icon }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <div class="d-flex align-items-center flex-wrap gap-1">
        <button
            v-for="r in reactions"
            :key="r.icon"
            type="button"
            class="wall-chip btn btn-sm d-inline-flex align-items-center gap-1"
            :class="r.mine ? 'wall-chip--mine' : ''"
            :data-reaction="r.icon"
            :aria-pressed="r.mine"
            @click="toggle(r.icon)"
        >
            <VibeIcon :icon="r.icon" /><span>{{ r.count }}</span>
        </button>

        <div class="dropdown">
            <button
                type="button"
                class="wall-chip btn btn-sm"
                data-bs-toggle="dropdown"
                aria-label="Add reaction"
                data-testid="add-reaction"
            >
                <VibeIcon icon="emoji-smile" /><span class="ms-1">+</span>
            </button>
            <div class="dropdown-menu p-2">
                <div class="d-flex flex-wrap gap-1" style="max-width: 10rem">
                    <button
                        v-for="icon in PICKER_ICONS"
                        :key="icon"
                        type="button"
                        class="btn btn-sm btn-light"
                        :data-pick="icon"
                        :aria-label="`React with ${icon}`"
                        @click="toggle(icon)"
                    >
                        <VibeIcon :icon="icon" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wall-chip {
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    border-radius: 1rem;
    padding: 0.1rem 0.5rem;
    line-height: 1.3;
}
.wall-chip--mine {
    border-color: var(--bs-primary);
    background: rgba(99, 102, 241, 0.12);
    color: var(--bs-primary);
}
</style>
