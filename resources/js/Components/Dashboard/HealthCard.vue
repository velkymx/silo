<script setup lang="ts">
export interface HealthItem {
    category: string;
    label: string;
    status: 'ok' | 'warn' | 'red' | 'info';
    detail: string;
}

export interface SystemHealth {
    attentionCount: number;
    attention: HealthItem[];
    facts: string[];
}

defineProps<{ systemHealth: SystemHealth }>();

const dotClass: Record<string, string> = {
    red: 'health__dot--red',
    warn: 'health__dot--warn',
};
</script>

<template>
    <section class="health-card" aria-label="System health">
        <h2 class="h6 text-muted text-uppercase fw-semibold mb-2 health-card__heading">System Health</h2>
        <div class="card">
            <div class="card-body">
                <template v-if="systemHealth.attentionCount > 0">
                    <p class="fw-semibold mb-2">
                        {{ systemHealth.attentionCount }}
                        {{ systemHealth.attentionCount === 1 ? 'item needs' : 'items need' }} attention.
                    </p>
                    <ul class="list-unstyled mb-0">
                        <li
                            v-for="(item, i) in systemHealth.attention"
                            :key="i"
                            class="d-flex align-items-start gap-2 mb-2"
                            :data-status="item.status"
                        >
                            <span class="health__dot flex-shrink-0 mt-1" :class="dotClass[item.status]" />
                            <span class="min-w-0">
                                <span class="d-block fw-semibold">{{ item.label }}</span>
                                <span class="d-block small text-muted">{{ item.detail }}</span>
                            </span>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <p class="fw-semibold mb-1">Everything looks healthy.</p>
                    <p class="small text-muted mb-0">{{ systemHealth.facts.join(' · ') }}</p>
                </template>
            </div>
        </div>
    </section>
</template>

<style scoped>
.health__dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 50%;
    display: inline-block;
}
.health__dot--red {
    background: var(--bs-danger);
}
.health__dot--warn {
    background: var(--bs-warning);
}
</style>
