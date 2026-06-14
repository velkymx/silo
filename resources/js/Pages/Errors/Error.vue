<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const props = defineProps<{ status: number }>();

interface ErrorCopy { title: string; message: string; icon: string }

const copy = computed<ErrorCopy>(() => {
    const map: Record<number, ErrorCopy> = {
        403: { title: 'Forbidden', message: 'You don’t have permission to view this page.', icon: 'shield-lock-fill' },
        404: { title: 'Not Found', message: 'The page or file you’re looking for doesn’t exist or was moved.', icon: 'compass' },
        419: { title: 'Page Expired', message: 'Your session expired. Please refresh and try again.', icon: 'hourglass-split' },
        429: { title: 'Too Many Requests', message: 'You’re going a bit fast. Wait a moment and try again.', icon: 'stopwatch' },
        500: { title: 'Server Error', message: 'Something went wrong on our end. Please try again later.', icon: 'bug-fill' },
        503: { title: 'Service Unavailable', message: 'We’re down for maintenance. Please check back soon.', icon: 'cone-striped' },
    };
    return map[props.status] ?? { title: 'Error', message: 'An unexpected error occurred.', icon: 'exclamation-triangle-fill' };
});

function goBack(): void {
    if (window.history.length > 1) window.history.back();
    else router.visit('/');
}
</script>

<template>
    <GuestLayout :title="`${status} — ${copy.title}`">
        <div class="text-center py-3">
            <VibeIcon :icon="copy.icon" class="display-4 text-secondary mb-3" />
            <div class="display-5 fw-bold">{{ status }}</div>
            <p class="text-muted">{{ copy.message }}</p>
            <div class="d-flex justify-content-center gap-2 mt-4">
                <VibeButton variant="secondary" outline @click="router.visit('/')">
                    <VibeIcon icon="house-door-fill" class="me-1" />Home
                </VibeButton>
                <VibeButton variant="primary" @click="goBack">
                    <VibeIcon icon="arrow-left" class="me-1" />Go back
                </VibeButton>
            </div>
        </div>
    </GuestLayout>
</template>
