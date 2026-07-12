<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ShellLayout from '../../../Layouts/ShellLayout.vue';

interface Execution {
    id: number;
    rule_id: number;
    rule_name: string | null;
    trigger_event: string;
    event_key: string;
    status: string;
    error: string | null;
    // New shape (workflow layer): a flat record of condition key -> raw value.
    // Old shape (rule layer): a record of condition key -> {matched, value, against}.
    conditions_evaluated: Record<string, unknown> | null;
    actions_executed: Array<{ type: string; ok?: boolean; error?: string; skipped?: string; activity_id?: string }> | null;
    created_at: string | null;
}

interface RuleRef { id: number; name: string; enabled: boolean }

const props = defineProps<{ executions: Execution[]; rules: RuleRef[]; filters: { rule: number | null; status: string | null } }>();

const activePane = ref('contents');
import { ref } from 'vue';

const statusBadge = (s: string): { cls: string; icon: string } => {
    if (s === 'matched') return { cls: 'text-bg-success', icon: 'check-circle-fill' };
    if (s === 'skipped') return { cls: 'text-bg-secondary', icon: 'dash-circle' };
    if (s === 'unsupported') return { cls: 'text-bg-warning', icon: 'hourglass-split' };
    return { cls: 'text-bg-danger', icon: 'exclamation-triangle-fill' };
};

const eventLabel: Record<string, string> = {};

function setRule(id: number | null): void {
    router.get('/rss/rules/logs', id ? { rule: id } : {}, { preserveState: true, replace: true });
}
function setStatus(s: string | null): void {
    router.get('/rss/rules/logs', { ...(props.filters.rule ? { rule: props.filters.rule } : {}), ...(s ? { status: s } : {}) }, { preserveState: true, replace: true });
}
function replay(e: Execution): void {
    if (!confirm(`Replay this “${e.trigger_event}” event? New executions will be recorded for every matching rule.`)) return;
    router.post(`/rss/rules/logs/${e.id}/replay`, {}, { preserveScroll: true, preserveState: true });
}
function conditionMatched(c: unknown): boolean {
    if (c && typeof c === 'object' && 'matched' in (c as Record<string, unknown>)) {
        return Boolean((c as Record<string, unknown>).matched);
    }
    return true;
}
function conditionValue(c: unknown): string {
    if (c && typeof c === 'object' && 'value' in (c as Record<string, unknown>)) {
        return JSON.stringify((c as Record<string, unknown>).value);
    }
    return JSON.stringify(c);
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <Link href="/rss/rules" class="btn btn-light btn-sm">
                    <VibeIcon icon="chevron-left" class="me-1" />Back to rules
                </Link>
                <h1 class="h5 mb-0 ms-2 d-flex align-items-center gap-2">
                    <VibeIcon icon="journal-text" class="text-primary" />Rule execution logs
                </h1>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <VibeFormSelect
                        :model-value="filters.rule ?? ''"
                        :options="[{ value: '', text: 'All rules' }, ...rules.map((r) => ({ value: r.id, text: r.name }))]"
                        @update:model-value="(v: string | number) => setRule(v ? Number(v) : null)"
                    />
                    <VibeFormSelect
                        :model-value="filters.status ?? ''"
                        :options="[{ value: '', text: 'All statuses' }, { value: 'matched', text: 'Matched' }, { value: 'skipped', text: 'Skipped' }, { value: 'unsupported', text: 'Unsupported' }, { value: 'failed', text: 'Failed' }]"
                        @update:model-value="(v: string) => setStatus(v || null)"
                    />
                </div>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1">
            <div class="px-3 pt-2">
                <div v-if="!executions.length" class="text-center text-muted py-5">
                    <VibeIcon icon="journal-text" class="display-6 mb-2 d-block" />
                    <p class="mb-0">No executions yet.</p>
                </div>
                <div v-for="e in executions" :key="e.id" class="card mb-2 log-card">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge" :class="statusBadge(e.status).cls">
                                <VibeIcon :icon="statusBadge(e.status).icon" class="me-1" />{{ e.status }}
                            </span>
                            <span class="badge text-bg-light">{{ e.trigger_event }}</span>
                            <span class="fw-semibold text-truncate">{{ e.rule_name ?? `Rule #${e.rule_id}` }}</span>
                            <VibeButton
                                variant="link"
                                size="sm"
                                class="ms-auto p-0 small"
                                title="Replay this event"
                                @click="replay(e)"
                            >
                                <VibeIcon icon="arrow-counterclockwise" class="me-1" />Replay
                            </VibeButton>
                            <span class="small text-muted">{{ e.created_at ? new Date(e.created_at).toLocaleString() : '' }}</span>
                        </div>
                        <details v-if="e.conditions_evaluated || e.actions_executed" class="small">
                            <summary>Details</summary>
                            <div v-if="e.conditions_evaluated && Object.keys(e.conditions_evaluated).length" class="mt-1">
                                <div class="text-uppercase small text-muted">Conditions</div>
                                <ul class="list-unstyled mb-2">
                                    <li v-for="(c, k) in e.conditions_evaluated" :key="k">
                                        <VibeIcon :icon="conditionMatched(c) ? 'check' : 'x'" :class="conditionMatched(c) ? 'text-success' : 'text-danger'" class="me-1" />
                                        <code>{{ k }}</code> = <code>{{ conditionValue(c) }}</code>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="e.actions_executed && e.actions_executed.length" class="mt-1">
                                <div class="text-uppercase small text-muted">Actions</div>
                                <ul class="list-unstyled mb-0">
                                    <li v-for="(a, i) in e.actions_executed" :key="i">
                                        <VibeIcon v-if="a.ok" icon="check" class="text-success me-1" />
                                        <VibeIcon v-else-if="a.skipped" icon="dash" class="text-muted me-1" />
                                        <VibeIcon v-else icon="x" class="text-danger me-1" />
                                        <code>{{ a.type }}</code>
                                        <span v-if="a.activity_id" class="text-muted ms-1">— {{ a.activity_id }}</span>
                                        <span v-if="a.error" class="text-danger ms-1">— {{ a.error }}</span>
                                        <span v-if="a.skipped" class="text-muted ms-1">— {{ a.skipped }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="e.error" class="text-danger small mt-1">{{ e.error }}</div>
                        </details>
                    </div>
                </div>
            </div>
        </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.log-card {
    border: 1px solid var(--bs-border-color);
}
</style>
