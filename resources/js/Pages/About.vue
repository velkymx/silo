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
                        <p>
                            Silo started the way most good tools do: with mild annoyance. I wanted a
                            file manager on my own server that did not treat me as the product. Then
                            the files wanted notes, the notes wanted links, the links wanted feeds,
                            and somewhere along the way an encrypted vault and a photo gallery moved
                            in. What you are using now is the result: one login, one permission
                            model, one search, on hardware you control.
                        </p>
                        <p class="mb-0">
                            I have been building for the web for 30 years, most of it shipping
                            software for other people. I was VP of
                            Engineering at ProcessMaker, where we scaled the team from 1 to more than
                            60 through a 45 million dollar Series A. I led an Agile transformation at
                            Grand Canyon Education that took sprint commitment success from 40 to 90
                            percent. I built Xsolla's US engineering team from zero. Silo is what I
                            built when nobody was writing the requirements but me.
                        </p>
                    </section>

                    <section>
                        <h2 class="h5">Work with me</h2>
                        <p>
                            <strong>What you get:</strong> the person who wrote every line of this
                            app, on your problem. Support contracts and custom features for Silo,
                            integrations with your stack, or your own Laravel, Vue, Node, or AI
                            project. If it made this page load, I can build with it.
                        </p>
                        <p>
                            <strong>How it works:</strong> remote engagements in prepaid blocks of 5,
                            10, or 20 hours, with 10 to 15 hours a week of availability. You get
                            senior architecture and working code, not a slide deck. I respond within
                            one business day.
                        </p>
                        <p>
                            <strong>Why believe me:</strong> you are looking at the portfolio.
                            Everything on this server, the versioned files, the SSRF-guarded feeds,
                            the checksummed backups that refuse to restore corruption, was designed,
                            built, and tested by one person. That is the level of care your project
                            gets.
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
