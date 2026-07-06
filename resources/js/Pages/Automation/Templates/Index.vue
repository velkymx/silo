<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellLayout from '../../../Layouts/ShellLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';

interface Template {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    icon: string;
    event_namespace: string;
    actions_count: number;
}

const props = defineProps<{ templates: Template[] }>();

const activePane = ref('contents');
const { confirm } = useConfirm();
const toast = useToast();

async function apply(t: Template): Promise<void> {
    if (!await confirm({
        title: 'Use this template',
        message: `Create a new rule from “${t.name}”? You'll be able to edit it after.`,
        confirmLabel: 'Create rule',
    })) return;
    router.post(`/rss/rules/templates/${t.id}/apply`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Rule “${t.name}” created`, { variant: 'success' }),
    });
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <h1 class="h5 mb-0 d-flex align-items-center gap-2">
                    <VibeIcon icon="lightning-charge-fill" class="text-primary" />Workflow templates
                </h1>
                <Link href="/rss/rules" class="btn btn-link btn-sm">Back to rules</Link>
            </div>
        </template>

        <template #contents>
            <div class="px-3 pt-3">
                <p class="text-muted small mb-3">Clone a template to create a rule in one click. You can edit conditions and actions afterwards.</p>
                <div class="row g-3">
                    <div v-for="t in templates" :key="t.id" class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <VibeIcon :icon="t.icon" class="text-primary fs-5" />
                                    <span class="fw-semibold">{{ t.name }}</span>
                                </div>
                                <p class="text-muted small flex-grow-1">{{ t.description }}</p>
                                <div class="d-flex align-items-center gap-2 small text-muted mb-3">
                                    <span class="badge text-bg-light">{{ t.event_namespace }}</span>
                                    <span>{{ t.actions_count }} action{{ t.actions_count === 1 ? '' : 's' }}</span>
                                </div>
                                <VibeButton variant="primary" size="sm" @click="apply(t)">
                                    <VibeIcon icon="plus-lg" class="me-1" />Use template
                                </VibeButton>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="!templates.length" class="text-center text-muted py-5">
                    <VibeIcon icon="lightning-charge" class="display-6 mb-2 d-block" />
                    <p class="mb-0">No templates available.</p>
                </div>
            </div>
        </template>
    </ShellLayout>
</template>
