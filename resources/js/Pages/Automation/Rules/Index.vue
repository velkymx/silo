<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ShellLayout from '../../../Layouts/ShellLayout.vue';
import { useToast } from '../../../composables/useToast';

interface Rule {
    id: number;
    name: string;
    description: string | null;
    enabled: boolean;
    scope: string;
    trigger_event: string;
    event_version: string;
    conditions_json: Record<string, unknown>;
    actions_json: Array<{ type: string; data?: Record<string, unknown> }>;
    run_count: number;
    last_run_at: string | null;
    updated_at: string | null;
}

const props = defineProps<{
    rules: Rule[];
    events: string[];
    eventDescriptions: Record<string, string>;
    actionTypes: string[];
    scopes: string[];
}>();

const activePane = ref('contents');
const toast = useToast();
const showForm = ref(false);
const editing = ref<Rule | null>(null);

const blank = {
    name: '',
    description: '',
    enabled: true,
    trigger_event: props.events[0] ?? 'rss.item.created',
    conditions_json: '{}',
    actions_json: '[]',
};

const form = useForm<typeof blank>({ ...blank });

const invalid = computed(() => {
    try {
        const c = JSON.parse(form.conditions_json);
        const a = JSON.parse(form.actions_json);
        return !c || typeof c !== 'object' || !Array.isArray(a) || a.length === 0;
    } catch { return true; }
});

function openAdd(): void {
    editing.value = null;
    Object.assign(form, { ...blank });
    form.clearErrors();
    showForm.value = true;
}
function openEdit(rule: Rule): void {
    editing.value = rule;
    Object.assign(form, {
        name: rule.name,
        description: rule.description ?? '',
        enabled: rule.enabled,
        trigger_event: rule.trigger_event,
        conditions_json: JSON.stringify(rule.conditions_json ?? {}, null, 2),
        actions_json: JSON.stringify(rule.actions_json ?? [], null, 2),
    });
    form.clearErrors();
    showForm.value = true;
}
function submit(): void {
    const payload = {
        name: form.name,
        description: form.description,
        enabled: form.enabled,
        trigger_event: form.trigger_event,
        conditions_json: JSON.parse(form.conditions_json),
        actions_json: JSON.parse(form.actions_json),
    };
    if (editing.value) {
        router.patch(`/rss/rules/${editing.value.id}`, payload, { preserveScroll: true, onSuccess: () => { showForm.value = false; } });
    } else {
        router.post('/rss/rules', payload, { preserveScroll: true, onSuccess: () => { showForm.value = false; } });
    }
}
function toggle(rule: Rule): void {
    router.post(`/rss/rules/${rule.id}/toggle`, {}, { preserveScroll: true, preserveState: true });
}
function remove(rule: Rule): void {
    if (!confirm(`Delete rule “${rule.name}”?`)) return;
    router.delete(`/rss/rules/${rule.id}`, { preserveScroll: true });
    toast.push(`Rule “${rule.name}” deleted`, { variant: 'danger' });
}

const actionLabels: Record<string, string> = {
    create_notification: 'Create notification',
    tag_item: 'Tag item',
    mark_starred: 'Mark as starred',
    save_bookmark: 'Save to bookmarks',
};
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <h1 class="h5 mb-0 d-flex align-items-center gap-2">
                    <VibeIcon icon="lightning-charge-fill" class="text-primary" />Automation rules
                </h1>
                <Link href="/rss/rules/templates" class="btn btn-link btn-sm">Templates</Link>
                <Link href="/rss/rules/logs" class="btn btn-link btn-sm">View logs</Link>
                <div class="ms-auto">
                    <VibeButton size="sm" variant="primary" @click="openAdd">
                        <VibeIcon icon="plus-lg" class="me-1" />New rule
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1">
            <div class="px-3 pt-2">
                <div v-if="!rules.length" class="text-center text-muted py-5">
                    <VibeIcon icon="lightning-charge" class="display-6 mb-2 d-block" />
                    <p class="mb-1 fw-semibold">No rules yet</p>
                    <p class="small">Wire up your first event → condition → action chain.</p>
                    <VibeButton size="sm" variant="primary" @click="openAdd"><VibeIcon icon="plus-lg" class="me-1" />Create rule</VibeButton>
                </div>
                <div v-for="rule in rules" :key="rule.id" class="rule-card card mb-2">
                    <div class="card-body d-flex align-items-start gap-3 py-2">
                        <VibeFormCheckbox :model-value="rule.enabled" @update:model-value="toggle(rule)" :title="rule.enabled ? 'Disable' : 'Enable'" @click.stop />
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold">{{ rule.name }}</span>
                                <span class="badge text-bg-light">{{ rule.trigger_event }}</span>
                                <span class="badge text-bg-secondary">{{ rule.scope }}</span>
                                <span v-if="rule.last_run_at" class="small text-muted ms-auto">last run {{ new Date(rule.last_run_at).toLocaleString() }} · {{ rule.run_count }}×</span>
                            </div>
                            <div class="small text-muted text-truncate" :title="eventDescriptions[rule.trigger_event]">
                                {{ eventDescriptions[rule.trigger_event] ?? '—' }}
                            </div>
                            <div class="small mt-1">
                                <span v-for="(action, i) in rule.actions_json" :key="i" class="badge text-bg-secondary me-1">
                                    {{ actionLabels[action.type] ?? action.type }}
                                </span>
                            </div>
                        </div>
                        <VibeButton variant="light" size="sm" @click="openEdit(rule)" title="Edit">
                            <VibeIcon icon="pencil" />
                        </VibeButton>
                        <VibeButton variant="light" size="sm" @click="remove(rule)" title="Delete">
                            <VibeIcon icon="trash" class="text-danger" />
                        </VibeButton>
                    </div>
                </div>
            </div>
        </div>
        </template>
    </ShellLayout>

    <VibeModal v-model="showForm" :title="editing ? 'Edit rule' : 'New rule'" size="lg" centered>
        <form @submit.prevent="submit">
            <div class="row">
                <div class="col-md-8">
                    <VibeFormGroup label="Name" :error="form.errors.name">
                        <VibeFormInput v-model="form.name" placeholder="Star Laravel security posts" required />
                    </VibeFormGroup>
                </div>
                <div class="col-md-4">
                    <VibeFormGroup label="Trigger event" :error="form.errors.trigger_event" help-text="Use * wildcards: rss.item.* or calendar.*">
                        <VibeFormSelect
                            v-model="form.trigger_event"
                            :options="events.map((e) => ({ value: e, text: e }))"
                        />
                    </VibeFormGroup>
                </div>
            </div>
            <VibeFormGroup label="Description" :error="form.errors.description">
                <VibeFormInput v-model="form.description" placeholder="Optional" />
            </VibeFormGroup>
            <VibeFormCheckbox v-model="form.enabled" label="Enabled" />

            <VibeFormGroup label="Conditions (JSON)" :error="form.errors.conditions_json" help-text='e.g. { "feed_title_contains": "Laravel", "title_contains": "security" }'>
                <textarea v-model="form.conditions_json" class="form-control font-monospace small" rows="4"></textarea>
            </VibeFormGroup>

            <VibeFormGroup label="Actions (JSON)" :error="form.errors.actions_json" :help-text="`Supported: ${actionTypes.join(', ')}`">
                <textarea v-model="form.actions_json" class="form-control font-monospace small" rows="4"></textarea>
            </VibeFormGroup>
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="showForm = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="invalid || form.processing" @click="submit">
                {{ editing ? 'Save' : 'Create rule' }}
            </VibeButton>
        </template>
    </VibeModal>
</template>

<style scoped>
.rule-card {
    border: 1px solid var(--bs-border-color);
}
</style>

