<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface RecentItem {
    id: number;
    type: string;
    severity: string;
    title: string;
    url: string | null;
    read_at: string | null;
    created_at: string | null;
}

const page = usePage();
const notifications = computed<{ unread_count: number; recent: RecentItem[] }>(() => {
    return (page.props.notifications as { unread_count: number; recent: RecentItem[] } | undefined) ?? { unread_count: 0, recent: [] };
});

const unread = computed(() => notifications.value.unread_count);
const recent = computed(() => notifications.value.recent);

const items = computed(() => {
    if (recent.value.length === 0) {
        return [
            { key: 'empty', text: 'No notifications', disabled: true },
            { key: 'view-all', text: 'View all', href: '/rss/notifications' },
        ];
    }
    return [
        ...recent.value.map((n) => ({
            key: String(n.id),
            id: n.id,
            severity: n.severity,
            title: n.title,
            url: n.url,
            read_at: n.read_at,
            created_at: n.created_at,
        })),
        { key: 'view-all', text: 'View all', href: '/rss/notifications' },
    ];
});

const severityClass = (severity: string): string => {
    if (severity === 'high') return 'text-danger';
    if (severity === 'low') return 'text-muted';
    return 'text-primary';
};

const severityIcon = (severity: string): string => {
    if (severity === 'high') return 'exclamation-triangle-fill';
    if (severity === 'low') return 'info-circle';
    return 'bell-fill';
};

const formatTime = (iso: string | null): string => {
    if (!iso) return '';
    const d = new Date(iso);
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return d.toLocaleDateString();
};

interface Payload { item: { key?: string; id?: number; url?: string | null; read_at?: string | null; created_at?: string | null; severity?: string; title?: string; text?: string } }

function onItemClick({ item }: Payload): void {
    if (item.key === 'view-all' || !item.id) return;
    if (!item.read_at) {
        router.post(`/rss/notifications/${item.id}/read`, {}, { preserveScroll: true, preserveState: true });
    }
    if (item.url) {
        window.open(item.url, '_blank', 'noopener');
    }
}

function markAll(e: Event): void {
    e.preventDefault();
    e.stopPropagation();
    router.post('/rss/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div class="notif-bell">
        <VibeDropdown size="sm" variant="light" menu-end auto-close="outside" :items="items" @item-click="onItemClick">
            <template #button>
                <span class="position-relative d-inline-flex align-items-center">
                    <VibeIcon icon="bell-fill" />
                    <span v-if="unread > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger notif-bell__dot">
                        {{ unread > 99 ? '99+' : unread }}
                    </span>
                </span>
            </template>
            <template #header>
                <div class="d-flex align-items-center w-100">
                    <span class="fw-semibold">Notifications</span>
                    <VibeButton v-if="unread > 0" size="sm" variant="link" class="ms-auto p-0 small" @click="markAll">Mark all read</VibeButton>
                </div>
            </template>
            <template #item="{ item }">
                <div v-if="item.key === 'empty'" class="text-muted text-center small py-2">
                    <VibeIcon icon="bell-slash" class="me-1" />No notifications
                </div>
                <div v-else-if="item.key === 'view-all'" class="text-center small text-primary py-1">View all</div>
                <div v-else class="d-flex align-items-start gap-2 py-2" :class="{ 'fw-semibold': !item.read_at }">
                    <VibeIcon :icon="severityIcon(item.severity ?? 'normal')" :class="severityClass(item.severity ?? 'normal')" class="mt-1 flex-shrink-0" />
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-truncate">{{ item.title }}</div>
                        <div class="small text-muted">{{ formatTime(item.created_at ?? null) }}</div>
                    </div>
                </div>
            </template>
        </VibeDropdown>
    </div>
</template>

<style scoped>
.notif-bell {
    display: inline-flex;
}
.notif-bell__dot {
    font-size: 0.6rem;
}
</style>
