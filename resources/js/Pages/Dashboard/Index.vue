<script setup lang="ts">
import { ref } from 'vue';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import JumpBackIn, { type JumpBackInItem } from '../../Components/Dashboard/JumpBackIn.vue';
import QuickActions from '../../Components/Dashboard/QuickActions.vue';

defineProps<{ jumpBackIn: JumpBackInItem | null }>();

const activePane = ref('contents');
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <h1 class="h5 mb-0 ms-2 d-flex align-items-center gap-2">
                    <VibeIcon icon="house-fill" class="text-primary" />Home
                </h1>
            </div>
        </template>

        <template #contents>
            <div class="dashboard-home mx-auto px-3 py-3 d-flex flex-column gap-3">
                <JumpBackIn :item="jumpBackIn" />
                <QuickActions />
            </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.dashboard-home {
    max-width: 52rem;
}
</style>
