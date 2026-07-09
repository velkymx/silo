<script setup lang="ts">
import { ref } from 'vue';
import ShellLayout from '../Layouts/ShellLayout.vue';

interface Developer {
    name: string;
    title: string;
    hire_url: string;
    linkedin: string;
}

defineProps<{ version: string; developer: Developer }>();

const activePane = ref('contents');
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <h1 class="h5 mb-0 ms-2 d-flex align-items-center gap-2">
                    <VibeIcon icon="info-circle-fill" class="text-primary" />About
                </h1>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1">
                <div class="about-page mx-auto px-3 py-4 d-flex flex-column gap-4">
                    <section>
                        <h2 class="h4 d-flex align-items-center gap-2">
                            <VibeIcon icon="rocket-takeoff-fill" class="text-primary" />
                            Silo
                            <span class="badge text-bg-secondary fw-normal" data-testid="version">v{{ version }}</span>
                        </h2>
                        <p class="mb-0">
                            One private workspace for your files, notes, links, feeds, and secrets.
                            Self-hosted, on your server, in your database. Nothing leaves your
                            infrastructure. Released under the MIT License.
                        </p>
                    </section>

                    <section>
                        <h2 class="h5">Built by {{ developer.name }}</h2>
                        <p class="text-muted small mb-2">{{ developer.title }}</p>
                        <p class="mb-0">
                            Silo started as a simple file manager and grew into the workspace you are
                            using now: one login, one permission model, one search across everything
                            you store. It was designed and built end to end by one developer who has
                            spent two decades shipping and leading engineering for platforms like
                            ProcessMaker, Grand Canyon Education, and Xsolla, and who believes
                            software you rely on should live on hardware you control.
                        </p>
                    </section>

                    <section>
                        <h2 class="h5">Support and customizations</h2>
                        <p>
                            Need help deploying Silo, want a feature built for your team, or have a
                            Laravel, Vue, or AI project of your own? I am available for hire for
                            support contracts, customizations, and consulting. Remote, with prepaid
                            hourly blocks, and I respond within one business day.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <a :href="developer.hire_url" target="_blank" rel="noopener" class="btn btn-primary" data-testid="hire-link">
                                <VibeIcon icon="briefcase" class="me-1" />Work with me
                            </a>
                            <a :href="developer.linkedin" target="_blank" rel="noopener" class="btn btn-outline-secondary" data-testid="linkedin-link">
                                <VibeIcon icon="linkedin" class="me-1" />LinkedIn
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.about-page {
    max-width: 44rem;
    width: 100%;
}
</style>
