<script setup>
defineProps({
    title: { type: String, default: '' },
});
const appName = import.meta.env.VITE_APP_NAME || 'Silo';
</script>

<template>
    <div class="min-vh-100 d-flex align-items-center bg-body-tertiary py-5">
        <VibeContainer>
            <VibeRow class="justify-content-center">
                <VibeCol :md="6" :lg="5">
                    <div class="text-center mb-4">
                        <VibeIcon icon="rocket-takeoff-fill" class="fs-1 text-primary" />
                        <h1 class="h4 mt-2 mb-0">{{ appName }}</h1>
                        <p class="text-muted small mb-0">Your Files Ready to Launch</p>
                    </div>
                    <VibeCard :header="title">
                        <slot />
                    </VibeCard>
                </VibeCol>
            </VibeRow>
        </VibeContainer>
    </div>
</template>
