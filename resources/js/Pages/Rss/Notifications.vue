<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import { http } from '../../lib/http';
import { notificationIcon } from '../../lib/notificationIcon';

interface Notification {
    id: number;
    type: string;
    severity: string;
    title: string;
    body: string | null;
    url: string | null;
    read_at: string | null;
    created_at: string | null;
}

const props = defineProps<{ notifications: Notification[]; unread: number; filters: { filter: string | null } }>();

const activePane = ref('contents');

function severityBadge(s: string): string {
    if (s === 'high') return 'text-bg-danger';
    if (s === 'low') return 'text-bg-light';
    return 'text-bg-primary';
}

async function open(n: Notification): Promise<void> {
    // When navigating away, mark read via fetch and await it first — a
    // router.post here would be cancelled by the location change, leaving the
    // notification unread.
    if (n.url) {
        if (!n.read_at) {
            try {
                await http.post(`/rss/notifications/${n.id}/read`);
            } catch {
                // non-fatal: proceed to the article regardless
            }
        }
        window.location.href = n.url;
        return;
    }
    // Staying on the page — use an Inertia visit so the unread count updates.
    if (!n.read_at) {
        router.post(`/rss/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true });
    }
}

function setFilter(f: string | null): void {
    router.get('/rss/notifications', f ? { filter: f } : {}, { preserveState: true, replace: true });
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <Link href="/rss" class="btn btn-secondary btn-sm">
                    <VibeIcon icon="chevron-left" class="me-1" />Back
                </Link>
                <h1 class="h5 mb-0 ms-2 d-flex align-items-center gap-2">
                    <VibeIcon icon="bell-fill" class="text-primary" />Notifications
                    <span v-if="unread" class="badge text-bg-danger ms-1">{{ unread }}</span>
                </h1>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <VibeFormSelect
                        :model-value="filters.filter ?? ''"
                        :options="[{ value: '', text: 'All' }, { value: 'unread', text: 'Unread' }, { value: 'high', text: 'High severity' }]"
                        @update:model-value="(v: string) => setFilter(v || null)"
                    />
                    <VibeButton
                        v-if="unread"
                        size="sm"
                        variant="secondary"
                        @click="router.post('/rss/notifications/read-all', {}, { preserveScroll: true, preserveState: true })"
                    >
                        <VibeIcon icon="check2-all" class="me-1" />Mark all read
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #contents>
            <div class="px-3 pt-2">
                <div v-if="!notifications.length" class="text-center text-muted py-5">
                    <VibeIcon icon="bell-slash" class="display-6 mb-2 d-block" />
                    <p class="mb-0">No notifications.</p>
                </div>
                <div
                    v-for="n in notifications"
                    :key="n.id"
                    class="notif-row d-flex align-items-start gap-2 p-2 mb-1 rounded"
                    :class="{ 'notif-row--unread': !n.read_at }"
                    role="button"
                    @click="open(n)"
                >
                    <span class="badge flex-shrink-0 d-inline-flex align-items-center" :class="severityBadge(n.severity)" :title="n.severity">
                        <VibeIcon :icon="notificationIcon(n.type)" />
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold text-truncate">{{ n.title }}</span>
                            <span class="small text-muted ms-auto">{{ n.created_at ? new Date(n.created_at).toLocaleString() : '' }}</span>
                        </div>
                        <div v-if="n.body" class="small text-muted text-truncate">{{ n.body }}</div>
                    </div>
                </div>
            </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.notif-row {
    cursor: pointer;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
}
.notif-row:hover {
    background: rgba(var(--bs-primary-rgb), 0.06);
}
.notif-row--unread {
    background: rgba(var(--bs-primary-rgb), 0.04);
    border-color: rgba(var(--bs-primary-rgb), 0.3);
}
</style>
