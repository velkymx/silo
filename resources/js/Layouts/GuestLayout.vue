<script setup>
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="min-vh-100 d-flex align-items-center bg-body-tertiary py-5">
        <VibeContainer>
            <VibeRow class="justify-content-center">
                <VibeCol :md="6" :lg="5">
                    <div class="text-center mb-4">
                        <VibeIcon icon="folder-fill" class="fs-1 text-primary" />
                        <h1 class="h4 mt-2">File Manager</h1>
                    </div>
                    <VibeCard :header="title">
                        <slot />
                    </VibeCard>
                </VibeCol>
            </VibeRow>
        </VibeContainer>
    </div>
</template>
