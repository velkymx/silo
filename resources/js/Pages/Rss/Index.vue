<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import RssItemRow from '../../Components/Rss/RssItemRow.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { usePageLoading } from '../../composables/usePageLoading';
import { http, HttpError } from '../../lib/http';
import { useRelativeTime } from '../../composables/useRelativeTime';

interface Feed {
    id: number;
    title: string;
    folder: string | null;
    enabled: boolean;
    muted: boolean;
    muted_at: string | null;
    favicon_url: string | null;
    refresh_interval_minutes: number;
    last_fetched_at: string | null;
    last_error: string | null;
    unread_count: number;
}

interface Item {
    id: number;
    feed_id: number;
    feed_title: string | null;
    feed_folder: string | null;
    guid: string;
    title: string;
    excerpt: string | null;
    author: string | null;
    url: string;
    published_at: string | null;
    is_read: boolean;
    is_starred: boolean;
}

const opmlInput = ref<HTMLInputElement | null>(null);
const opmlUrlOpen = ref(false);
function onOpmlChosen(e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    router.post('/rss/opml/import', { opml: file }, { forceFormData: true, preserveScroll: true });
    input.value = '';
}

const opmlUrl = ref('');
const importingUrl = ref(false);
function importOpmlFromUrl(): void {
    const url = opmlUrl.value.trim();
    if (!url) return;
    // Drive the spinner from the real request lifecycle — router.post returns
    // void, so awaiting it cleared the flag (and the input) immediately.
    router.post('/rss/opml/import-url', { url }, {
        onStart: () => { importingUrl.value = true; },
        onFinish: () => { importingUrl.value = false; },
        onSuccess: () => { opmlUrl.value = ''; },
    });
}

const detecting = ref(false);
async function detectFeed(): Promise<void> {
    const url = addForm.url.trim();
    if (!url) {
        addForm.setError('url', 'Paste a page URL first, then click Detect.');
        return;
    }
    detecting.value = true;
    try {
        const data = await http.post<{ url: string; title?: string | null; source: string }>('/rss/discover', { url });
        addForm.url = data.url;
        if (data.title && !addForm.title) {
            addForm.title = data.title;
        }
        addForm.clearErrors('url');
        toast.push(data.url === data.source ? 'URL already points at a feed.' : `Found ${data.url}`, { variant: 'success' });
    } catch (e) {
        if (e instanceof HttpError) {
            const message = (e.data as { message?: string } | null)?.message;
            toast.push(message ?? 'No feed found on that page.', { variant: 'danger' });
        } else {
            toast.push('Detection failed. Check the URL and try again.', { variant: 'danger' });
        }
    } finally {
        detecting.value = false;
    }
}

const props = defineProps<{
    feeds: Feed[];
    items: Item[];
    itemsNextCursor?: string | null;
    filters: { filter: string | null; feed: number | null; search: string | null; show_muted: boolean };
    counts: { unread: number; starred: number; today: number; yesterday: number; week: number; month: number; recent: number; read_recent: number; feeds: number; muted: number };
}>();

const { confirm } = useConfirm();
const toast = useToast();
const { loading } = usePageLoading();
const { ago: fmtRelative } = useRelativeTime();

const activePane = ref<'folders' | 'contents' | 'detail'>('contents');
const selectedFeedId = ref<number | null>(props.filters.feed ?? null);
const selectedItemId = ref<number | null>(null);

// Load-more: keep a local copy of items so successive pages append to the
// same list rather than replace it. itemsNextCursor comes from the server
// as a string when more pages are available, null when exhausted.
const displayedItems = ref<Item[]>([...props.items]);
const currentCursor = ref<string | null>(props.itemsNextCursor ?? null);
const loadingMore = ref(false);
watch(() => props.items, (next) => {
    // A load-more visit is a partial reload that also updates props.items;
    // its onSuccess appends the new page, so skip the reset or the two race
    // and the appended page clobbers the accumulated list.
    if (loadingMore.value) return;
    // Server pushed a fresh page (e.g. search/filter changed) — reset the list.
    displayedItems.value = [...next];
    currentCursor.value = props.itemsNextCursor ?? null;
}, { flush: 'post' });

async function loadMore(): Promise<void> {
    if (!currentCursor.value || loadingMore.value) return;
    loadingMore.value = true;
    try {
        const params: Record<string, string | number> = { cursor: currentCursor.value };
        if (props.filters.filter) params.filter = props.filters.filter;
        if (props.filters.feed) params.feed = props.filters.feed;
        if (props.filters.show_muted) params.show_muted = 1;
        applyAdvancedFilters(params);
        await router.get('/rss', params, {
            only: ['items', 'itemsNextCursor'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const incoming = (page.props.items as Item[]) ?? [];
                const known = new Set(displayedItems.value.map((i) => i.id));
                for (const it of incoming) {
                    if (!known.has(it.id)) displayedItems.value.push(it);
                }
            },
        });
    } finally {
        loadingMore.value = false;
    }
}
const showAddFeed = ref(false);
const showEditFeed = ref(false);
const editingFeed = ref<Feed | null>(null);
const authorFilter = ref('');
const excludeFilter = ref('');

function applyAdvancedFilters(extra: Record<string, string | number>): Record<string, string | number> {
    if (authorFilter.value.trim()) extra.author = authorFilter.value.trim();
    if (excludeFilter.value.trim()) extra.exclude = excludeFilter.value.trim();
    return extra;
}

watch([authorFilter, excludeFilter], () => {
    const params: Record<string, string | number> = {};
    if (props.filters.filter) params.filter = props.filters.filter;
    if (props.filters.feed) params.feed = props.filters.feed;
    if (props.filters.show_muted) params.show_muted = 1;
    applyAdvancedFilters(params);
    router.get('/rss', params, { preserveState: true, replace: true });
});

const grouped = computed(() => {
    const map = new Map<string, Feed[]>();
    for (const f of props.feeds) {
        if (f.muted) continue;
        const key = f.folder || 'Uncategorized';
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(f);
    }
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b));
});

function feedIconClass(feed: Feed): string {
    if (!feed.enabled) return 'text-muted';
    if (feed.unread_count === 0) return 'text-muted opacity-50';
    return 'text-warning';
}

const mutedFeeds = computed(() => props.feeds.filter((f) => f.muted));

function toggleShowMuted(): void {
    router.get('/rss', { ...(props.filters.filter ? { filter: props.filters.filter } : {}), show_muted: props.filters.show_muted ? 0 : 1 }, { preserveState: true, replace: true });
}

const filteredItems = computed(() => {
    let list = displayedItems.value;
    if (selectedFeedId.value !== null) {
        list = list.filter((i) => i.feed_id === selectedFeedId.value);
    }
    if (props.filters.filter === 'starred') {
        list = list.filter((i) => i.is_starred);
    } else if (props.filters.filter === 'unread') {
        list = list.filter((i) => !i.is_read);
    }
    return list;
});

const selectedItem = computed(() => displayedItems.value.find((i) => i.id === selectedItemId.value) ?? null);
const selectedFeed = computed(() => props.feeds.find((f) => f.id === selectedFeedId.value) ?? null);

function selectFeed(id: number | null): void {
    selectedFeedId.value = id;
    activePane.value = 'contents';
    router.get('/rss', id ? { feed: id } : {}, { preserveState: true, replace: true });
}

// Smart-folder / inbox selection — one navigation. (Calling selectFeed(null)
// and then router.get() separately fired two competing visits per click.)
function selectFilter(filter: string | null): void {
    selectedFeedId.value = null;
    activePane.value = 'contents';
    router.get('/rss', filter ? { filter } : {}, { preserveState: true, replace: true });
}

function selectItem(id: number): void {
    selectedItemId.value = id;
    activePane.value = 'detail';
    const item = displayedItems.value.find((i) => i.id === id);
    if (item && !item.is_read) {
        // Optimistically flip local state so the detail pane and row update
        // without a full Inertia reload that would reset the appended list.
        item.is_read = true;
        http.post(`/rss/items/${id}/read`).catch(() => {
            item.is_read = false;
            toast.push('Could not mark as read.', { variant: 'danger' });
        });
    }
}

// Item bookkeeping (read / unread / star) mutates local state optimistically
// and persists via fetch. Using router.post would fire a full Inertia visit,
// flashing the list skeleton (usePageLoading) and resetting displayedItems.
function markSelectedRead(): void {
    const item = selectedItem.value;
    if (!item || item.is_read) return;
    item.is_read = true;
    http.post(`/rss/items/${item.id}/read`).catch(() => {
        item.is_read = false;
        toast.push('Could not mark as read.', { variant: 'danger' });
    });
}

function markSelectedUnread(): void {
    const item = selectedItem.value;
    if (!item || !item.is_read) return;
    item.is_read = false;
    http.post(`/rss/items/${item.id}/unread`).catch(() => {
        item.is_read = true;
        toast.push('Could not mark as unread.', { variant: 'danger' });
    });
}

function toggleStar(item: Item): void {
    const prev = item.is_starred;
    item.is_starred = !item.is_starred;
    http.post(`/rss/items/${item.id}/star`).catch(() => {
        item.is_starred = prev;
        toast.push('Could not update star.', { variant: 'danger' });
    });
}

async function markAllRead(): Promise<void> {
    if (!await confirm({ title: 'Mark all as read', message: 'Mark every visible item as read?', confirmLabel: 'Mark all' })) return;
    const payload: Record<string, string | number> = {};
    if (selectedFeedId.value) payload.feed = selectedFeedId.value;
    if (props.filters.filter) payload.filter = props.filters.filter;
    // Include the advanced filters so the server marks exactly the visible set.
    applyAdvancedFilters(payload);
    router.post('/rss/items/mark-all-read', payload, { preserveScroll: true });
    toast.push('Marked as read', { variant: 'success' });
}

async function refreshAll(): Promise<void> {
    if (!await confirm({ title: 'Refresh feeds', message: 'Queue a refresh for every enabled feed?', confirmLabel: 'Refresh' })) return;
    router.post('/rss/feeds/refresh-all', {}, { preserveScroll: true });
    toast.push('Refreshing feeds in the background…', { variant: 'info' });
}

const feedMenu = (feed: Feed) => [
    { text: 'Refresh', action: 'refresh', icon: 'arrow-repeat' },
    { text: 'Edit', action: 'edit', icon: 'pencil' },
    { text: feed.muted ? 'Unmute' : 'Mute', action: feed.muted ? 'unmute' : 'mute', icon: feed.muted ? 'bell-fill' : 'bell-slash' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];
function onFeedMenu(feed: Feed, { item }: { item: { action?: string } }): void {
    if (item.action === 'refresh') refreshOne(feed);
    if (item.action === 'edit') openEdit(feed);
    if (item.action === 'mute') muteFeed(feed);
    if (item.action === 'unmute') unmuteFeed(feed);
    if (item.action === 'delete') deleteFeed(feed);
}

function refreshOne(feed: Feed): void {
    router.post(`/rss/feeds/${feed.id}/refresh`, {}, { preserveScroll: true });
    toast.push(`Refreshing “${feed.title}”…`, { variant: 'info' });
}

function muteFeed(feed: Feed): void {
    router.post(`/rss/feeds/${feed.id}/mute`, {}, { preserveScroll: true });
}

function unmuteFeed(feed: Feed): void {
    router.post(`/rss/feeds/${feed.id}/unmute`, {}, { preserveScroll: true });
}

interface Stats {
    articles_today: number;
    articles_per_day: number;
    articles_per_week: number;
    last_success_at: string | null;
    avg_frequency_hours: number | null;
    success_rate: number | null;
    failed_count: number;
    unread_total: number;
    feeds_count: number;
    per_feed: Array<{ id: number; title: string; folder: string | null; count: number; last_fetched_at: string | null; last_success_at: string | null; last_error: string | null }>;
}
const stats = ref<Stats | null>(null);
const statsOpen = ref(false);
const statsLoading = ref(false);
async function toggleStats(): Promise<void> {
    statsOpen.value = !statsOpen.value;
    // Refetch every time the panel opens so it isn't stale after a refresh.
    if (statsOpen.value) {
        statsLoading.value = true;
        try {
            stats.value = await http.get<Stats>('/rss/stats');
        } catch (e) {
            toast.push('Could not load stats.', { variant: 'danger' });
        } finally {
            statsLoading.value = false;
        }
    }
}

async function deleteFeed(feed: Feed): Promise<void> {
    if (!await confirm({ title: 'Delete feed', message: `Delete “${feed.title}” and all its items?`, confirmLabel: 'Delete', variant: 'danger' })) return;
    router.delete(`/rss/feeds/${feed.id}`, { preserveScroll: true });
    toast.push(`Feed “${feed.title}” deleted`, { variant: 'danger' });
}

const addForm = useForm({ title: '', url: '', folder: '', refresh_interval_minutes: 60, enabled: true });
function openAdd(): void {
    addForm.reset();
    addForm.clearErrors();
    showAddFeed.value = true;
}
function submitAdd(): void {
    addForm.post('/rss/feeds', { preserveScroll: true, onSuccess: () => { showAddFeed.value = false; } });
}

const editForm = useForm({ title: '', folder: '', refresh_interval_minutes: 60, enabled: true });
function openEdit(feed: Feed): void {
    editingFeed.value = feed;
    editForm.title = feed.title;
    editForm.folder = feed.folder ?? '';
    editForm.refresh_interval_minutes = feed.refresh_interval_minutes;
    editForm.enabled = feed.enabled;
    editForm.clearErrors();
    showEditFeed.value = true;
}
function submitEdit(): void {
    if (!editingFeed.value) return;
    editForm.patch(`/rss/feeds/${editingFeed.value.id}`, { preserveScroll: true, onSuccess: () => { showEditFeed.value = false; } });
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="true" :folders-visible="true">
        <template #viewNav>
            <div class="px-2 py-2 d-flex flex-column gap-1">
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFeedId === null && !filters.filter }"
                    @click="selectFilter(null)"
                >
                    <VibeIcon icon="inbox-fill" class="text-primary" />
                    <span class="flex-grow-1">Inbox</span>
                    <span v-if="counts.unread" class="badge text-bg-light">{{ counts.unread }}</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'starred' }"
                    @click="selectFilter('starred')"
                >
                    <VibeIcon icon="star-fill" class="text-warning" />
                    <span class="flex-grow-1">Starred</span>
                    <span class="badge text-bg-light">{{ counts.starred }}</span>
                </button>
                <hr class="my-2">
                <div class="text-uppercase small text-muted px-2 mb-1">Smart folders</div>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'today' }"
                    @click="selectFilter('today')"
                >
                    <VibeIcon icon="calendar-day" class="text-primary" />
                    <span class="flex-grow-1">Today</span>
                    <span v-if="counts.today" class="badge text-bg-light">{{ counts.today }}</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'yesterday' }"
                    @click="selectFilter('yesterday')"
                >
                    <VibeIcon icon="calendar-minus" class="text-primary" />
                    <span class="flex-grow-1">Yesterday</span>
                    <span v-if="counts.yesterday" class="badge text-bg-light">{{ counts.yesterday }}</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'week' }"
                    @click="selectFilter('week')"
                >
                    <VibeIcon icon="calendar-week" class="text-primary" />
                    <span class="flex-grow-1">This week</span>
                    <span v-if="counts.week" class="badge text-bg-light">{{ counts.week }}</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'month' }"
                    @click="selectFilter('month')"
                >
                    <VibeIcon icon="calendar3" class="text-primary" />
                    <span class="flex-grow-1">Last 30 days</span>
                    <span v-if="counts.month" class="badge text-bg-light">{{ counts.month }}</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'recent' }"
                    @click="selectFilter('recent')"
                >
                    <VibeIcon icon="clock-history" class="text-primary" />
                    <span class="flex-grow-1">Recently added</span>
                    <span v-if="counts.recent" class="badge text-bg-light">{{ counts.recent }}</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'top_feeds' }"
                    @click="selectFilter('top_feeds')"
                >
                    <VibeIcon icon="fire" class="text-primary" />
                    <span class="flex-grow-1">Most active feeds</span>
                </button>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: filters.filter === 'read' }"
                    @click="selectFilter('read')"
                >
                    <VibeIcon icon="check2-circle" class="text-primary" />
                    <span class="flex-grow-1">Recently read</span>
                    <span v-if="counts.read_recent" class="badge text-bg-light">{{ counts.read_recent }}</span>
                </button>
                <hr class="my-2">
                <div v-for="[folder, feeds] in grouped" :key="folder" class="mb-2">
                    <div class="text-uppercase small text-muted px-2 mb-1">{{ folder }}</div>
                    <div
                        v-for="feed in feeds"
                        :key="feed.id"
                        class="side-row w-100 d-flex align-items-center gap-2 px-2 py-1 rounded"
                        :class="{ active: selectedFeedId === feed.id, 'side-row--idle': feed.enabled && feed.unread_count === 0 }"
                    >
                        <button
                            type="button"
                            class="flex-grow-1 d-flex align-items-center gap-2 text-start border-0 bg-transparent p-0 text-truncate"
                            @click="selectFeed(feed.id)"
                        >
                            <img
                                v-if="feed.favicon_url"
                                :src="feed.favicon_url"
                                alt=""
                                width="16"
                                height="16"
                                class="rss-row__favicon flex-shrink-0"
                                loading="lazy"
                                @error="($event.target as HTMLImageElement).style.display = 'none'"
                            >
                            <VibeIcon v-else :icon="feed.enabled ? 'rss-fill' : 'rss'" :class="feedIconClass(feed)" />
                            <span class="flex-grow-1 text-truncate" :title="feed.title">{{ feed.title }}</span>
                        </button>
                        <span v-if="feed.unread_count > 0" class="badge text-bg-light">{{ feed.unread_count }}</span>
                        <VibeDropdown size="sm" variant="secondary" menu-end title="Feed actions" :items="feedMenu(feed)" @click.stop @item-click="onFeedMenu(feed, $event)">
                            <template #button><VibeIcon icon="three-dots" /><span class="visually-hidden">Feed actions</span></template>
                            <template #item="{ item }">
                                <VibeIcon :icon="item.icon" class="me-2" :class="item.action === 'delete' ? 'text-danger' : ''" /><span :class="item.action === 'delete' ? 'text-danger' : ''">{{ item.text }}</span>
                            </template>
                        </VibeDropdown>
                    </div>
                </div>
                <template v-if="mutedFeeds.length">
                    <hr class="my-2">
                    <div class="text-uppercase small text-muted px-2 mb-1 d-flex align-items-center">
                        <VibeIcon icon="bell-slash" class="me-1" />Muted ({{ mutedFeeds.length }})
                    </div>
                    <div
                        v-for="feed in mutedFeeds"
                        :key="feed.id"
                        class="side-row side-row--muted w-100 d-flex align-items-center gap-2 px-2 py-1 rounded"
                    >
                        <span class="flex-grow-1 d-flex align-items-center gap-2 text-truncate text-muted">
                            <VibeIcon icon="bell-slash" />
                            <span class="flex-grow-1 text-truncate" :title="feed.title">{{ feed.title }}</span>
                        </span>
                        <VibeDropdown size="sm" variant="secondary" menu-end title="Feed actions" :items="feedMenu(feed)" @item-click="onFeedMenu(feed, $event)">
                            <template #button><VibeIcon icon="three-dots" /><span class="visually-hidden">Feed actions</span></template>
                            <template #item="{ item }">
                                <VibeIcon :icon="item.icon" class="me-2" :class="item.action === 'delete' ? 'text-danger' : ''" /><span :class="item.action === 'delete' ? 'text-danger' : ''">{{ item.text }}</span>
                            </template>
                        </VibeDropdown>
                    </div>
                </template>
                <button
                    v-if="counts.muted > 0 && !filters.show_muted"
                    type="button"
                    class="btn btn-link btn-sm text-decoration-none px-2 mt-1"
                    @click="toggleShowMuted"
                >
                    <VibeIcon icon="bell-slash" class="me-1" />Show {{ counts.muted }} muted feed(s)
                </button>
                <button
                    v-else-if="filters.show_muted"
                    type="button"
                    class="btn btn-link btn-sm text-decoration-none px-2 mt-1"
                    @click="toggleShowMuted"
                >
                    <VibeIcon icon="bell-fill" class="me-1" />Hide muted
                </button>
                <hr class="my-2">
                <button
                    type="button"
                    class="btn btn-link btn-sm text-decoration-none px-2 d-flex align-items-center"
                    @click="toggleStats"
                >
                    <VibeIcon :icon="statsOpen ? 'chevron-down' : 'chevron-right'" class="me-1" />
                    Stats
                    <VibeSpinner v-if="statsLoading" size="sm" class="ms-2" />
                </button>
                <div v-if="statsOpen && stats" class="px-2 pb-2 small text-muted">
                    <div class="d-flex justify-content-between">
                        <span><VibeIcon icon="plus-circle" class="me-1" />Today</span>
                        <span class="text-body">{{ stats.articles_today }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><VibeIcon icon="envelope" class="me-1" />Unread</span>
                        <span class="text-body">{{ stats.unread_total }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><VibeIcon icon="calendar-day" class="me-1" />Per day</span>
                        <span class="text-body">{{ stats.articles_per_day }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><VibeIcon icon="calendar-week" class="me-1" />Per week</span>
                        <span class="text-body">{{ stats.articles_per_week }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><VibeIcon icon="arrow-repeat" class="me-1" />Last fetch</span>
                        <span class="text-body">{{ fmtRelative(stats.last_success_at) }}</span>
                    </div>
                    <div v-if="stats.avg_frequency_hours !== null" class="d-flex justify-content-between">
                        <span><VibeIcon icon="clock-history" class="me-1" />Avg cadence</span>
                        <span class="text-body">~{{ stats.avg_frequency_hours }}h</span>
                    </div>
                    <div v-if="stats.success_rate !== null" class="d-flex justify-content-between">
                        <span><VibeIcon icon="heart-pulse" class="me-1" />Health</span>
                        <span :class="stats.success_rate >= 80 ? 'text-success' : stats.success_rate >= 50 ? 'text-warning' : 'text-danger'">{{ stats.success_rate }}%</span>
                    </div>
                    <div v-if="stats.failed_count > 0" class="d-flex justify-content-between">
                        <span><VibeIcon icon="exclamation-triangle" class="me-1 text-warning" />Failing</span>
                        <span class="text-body">{{ stats.failed_count }}</span>
                    </div>
                    <hr class="my-1">
                    <div class="text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.04em">Per feed</div>
                    <div v-for="f in stats.per_feed" :key="f.id" class="d-flex justify-content-between text-truncate">
                        <span class="text-truncate me-1" :title="f.title">
                            <VibeIcon v-if="f.last_error" icon="exclamation-triangle" class="text-warning me-1" />
                            {{ f.title }}
                        </span>
                        <span class="text-body flex-shrink-0">{{ f.count }}</span>
                    </div>
                </div>
            </div>
        </template>

        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <h1 class="h5 mb-0 d-flex align-items-center gap-2">
                    <VibeIcon icon="rss-fill" />
                    <span>{{ selectedFeed ? selectedFeed.title : (filters.filter === 'starred' ? 'Starred' : 'Inbox') }}</span>
                </h1>
                <VibeFormInput
                    v-model="authorFilter"
                    type="text"
                    placeholder="Author…"
                    class="ms-3 flex-grow-0"
                    style="width: 110px"
                    no-wrapper
                />
                <VibeFormInput
                    v-model="excludeFilter"
                    type="text"
                    placeholder="Exclude…"
                    class="flex-grow-0"
                    style="width: 110px"
                    no-wrapper
                />
                <div class="ms-auto d-flex align-items-center gap-2">
                    <VibeButton variant="secondary" size="sm" :title="`${counts.unread} unread`" :disabled="counts.unread === 0" @click="markAllRead">
                        <VibeIcon icon="check2-all" class="me-1" />Mark all read
                    </VibeButton>
                    <VibeButton variant="secondary" size="sm" title="Refresh all feeds" @click="refreshAll">
                        <VibeIcon icon="arrow-repeat" />
                    </VibeButton>
                    <VibeButton variant="secondary" size="sm" title="Import an OPML subscription file" aria-label="Import OPML" @click="opmlInput?.click()">
                        <VibeIcon icon="upload" class="me-1" />Import
                    </VibeButton>
                    <VibeButton variant="secondary" size="sm" title="Import OPML from a URL (Feedly, Inoreader, NetNewsWire)" aria-label="Import OPML from URL" @click="opmlUrlOpen = !opmlUrlOpen">
                        <VibeIcon icon="link-45deg" class="me-1" />From URL
                    </VibeButton>
                    <a href="/rss/opml/export" class="btn btn-secondary btn-sm" download title="Export all feeds as OPML" aria-label="Export OPML">
                        <VibeIcon icon="download" class="me-1" />Export
                    </a>
                    <input ref="opmlInput" type="file" accept=".opml,.xml,text/xml,application/xml" class="d-none" @change="onOpmlChosen">
                    <div v-if="opmlUrlOpen" class="d-flex gap-1 mt-1">
                        <VibeFormInput v-model="opmlUrl" type="url" placeholder="https://feedly.com/export/opml" size="sm" no-wrapper class="flex-grow-1" />
                        <VibeButton size="sm" variant="primary" :disabled="importingUrl || !opmlUrl.trim()" @click="importOpmlFromUrl">
                            <VibeSpinner v-if="importingUrl" size="sm" />
                            <VibeIcon v-else icon="cloud-download" />
                        </VibeButton>
                    </div>
                    <VibeButton size="sm" variant="primary" @click="openAdd">
                        <VibeIcon icon="plus-lg" class="me-1" />Add feed
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #contents>
            <LoadingSkeleton v-if="loading" :rows="6" :cols="1" />
            <div v-else class="rss-list overflow-auto flex-grow-1 px-2 pt-2">
                <EmptyState v-if="filteredItems.length === 0" icon="rss" title="Nothing in the inbox" hint="Add a feed to start ingesting." />
                <RssItemRow
                    v-for="item in filteredItems"
                    :key="item.id"
                    :item="item"
                    :selected="item.id === selectedItemId"
                    @click="selectItem(item.id)"
                    @toggle-star="toggleStar(item)"
                />
                <div v-if="currentCursor" class="text-center my-3">
                    <VibeButton variant="secondary" outline :disabled="loadingMore" @click="loadMore">
                        <VibeSpinner v-if="loadingMore" size="sm" class="me-1" />
                        Load more
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #detail>
            <div v-if="selectedItem" class="rss-detail h-100 d-flex flex-column p-3 overflow-auto">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <div class="flex-grow-1 min-w-0">
                        <a :href="selectedItem.url" target="_blank" rel="noopener" class="h5 text-break d-block mb-1">{{ selectedItem.title }}</a>
                        <div class="small text-muted">
                            <VibeIcon icon="rss-fill" class="me-1" />{{ selectedItem.feed_title }}
                            <template v-if="selectedItem.author"> · {{ selectedItem.author }}</template>
                            <template v-if="selectedItem.published_at"> · {{ new Date(selectedItem.published_at).toLocaleString() }}</template>
                        </div>
                    </div>
                    <VibeButton variant="link" size="sm" :title="selectedItem.is_starred ? 'Unstar' : 'Star'" @click="toggleStar(selectedItem)">
                        <VibeIcon :icon="selectedItem.is_starred ? 'star-fill' : 'star'" :class="selectedItem.is_starred ? 'text-warning' : 'text-muted'" />
                    </VibeButton>
                </div>
                <a :href="selectedItem.url" target="_blank" rel="noopener" class="text-break small text-muted d-block mb-3">{{ selectedItem.url }}</a>
                <div v-if="selectedItem.excerpt" class="rss-excerpt mb-3">{{ selectedItem.excerpt }}</div>
                <div class="mt-auto d-flex gap-2 flex-wrap">
                    <VibeButton v-if="!selectedItem.is_read" variant="primary" @click="markSelectedRead">
                        <VibeIcon icon="check2" class="me-1" />Mark read
                    </VibeButton>
                    <VibeButton v-else variant="secondary" @click="markSelectedUnread">
                        <VibeIcon icon="circle" class="me-1" />Mark unread
                    </VibeButton>
                    <VibeButton :href="selectedItem.url" target="_blank" rel="noopener" variant="primary">
                        <VibeIcon icon="box-arrow-up-right" class="me-1" />Open original
                    </VibeButton>
                    <Link v-if="selectedItem" :href="`/rss/items/${selectedItem.id}`" class="btn btn-secondary">
                        <VibeIcon icon="arrows-fullscreen" class="me-1" />Full view
                    </Link>
                </div>
            </div>
            <div v-else class="rss-detail h-100 d-none d-md-flex align-items-center justify-content-center text-muted p-4">
                <EmptyState icon="rss" title="No article selected" hint="Pick an article from the list to read it here." />
            </div>
        </template>
    </ShellLayout>

    <VibeModal v-model="showAddFeed" title="Add RSS feed" centered>
        <form @submit.prevent="submitAdd">
            <VibeFormGroup label="Title" :error="addForm.errors.title">
                <VibeFormInput v-model="addForm.title" placeholder="Laravel News" required />
            </VibeFormGroup>
            <VibeFormGroup label="Feed URL" :error="addForm.errors.url" help-text="Paste a page URL and click Detect, or paste the feed URL directly.">
                <div class="d-flex gap-2">
                    <VibeFormInput v-model="addForm.url" type="url" placeholder="https://…" required class="flex-grow-1" />
                    <VibeButton type="button" variant="secondary" outline :disabled="detecting" @click="detectFeed">
                        <VibeSpinner v-if="detecting" size="sm" class="me-1" />
                        <VibeIcon v-else icon="search" class="me-1" />Detect
                    </VibeButton>
                </div>
            </VibeFormGroup>
            <VibeFormGroup label="Folder" :error="addForm.errors.folder" help-text="Optional grouping (e.g. 'Tech').">
                <VibeFormInput v-model="addForm.folder" placeholder="Tech" />
            </VibeFormGroup>
            <VibeFormGroup label="Refresh interval (minutes)" :error="addForm.errors.refresh_interval_minutes">
                <VibeFormInput v-model="addForm.refresh_interval_minutes" type="number" min="5" max="1440" />
            </VibeFormGroup>
            <VibeFormCheckbox v-model="addForm.enabled" label="Enabled" />
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="showAddFeed = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="addForm.processing" @click="submitAdd">
                <VibeIcon icon="plus-lg" class="me-1" />Add feed
            </VibeButton>
        </template>
    </VibeModal>

    <VibeModal v-model="showEditFeed" :title="`Edit “${editingFeed?.title}”`" centered>
        <form @submit.prevent="submitEdit">
            <VibeFormGroup label="Title" :error="editForm.errors.title">
                <VibeFormInput v-model="editForm.title" required />
            </VibeFormGroup>
            <VibeFormGroup label="Folder" :error="editForm.errors.folder">
                <VibeFormInput v-model="editForm.folder" />
            </VibeFormGroup>
            <VibeFormGroup label="Refresh interval (minutes)" :error="editForm.errors.refresh_interval_minutes">
                <VibeFormInput v-model="editForm.refresh_interval_minutes" type="number" min="5" max="1440" />
            </VibeFormGroup>
            <VibeFormCheckbox v-model="editForm.enabled" label="Enabled" />
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="showEditFeed = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="editForm.processing" @click="submitEdit">Save</VibeButton>
        </template>
    </VibeModal>
</template>

<style scoped>
.side-row {
    color: var(--bs-body-color);
}
.side-row:hover {
    background: rgba(var(--bs-primary-rgb), 0.08);
}
.side-row.active {
    background: rgba(var(--bs-primary-rgb), 0.14);
    color: var(--bs-primary);
    font-weight: 500;
}
/* Fade the row CONTENT, never the row: row-level opacity creates a
   stacking context and makes the open feed-actions dropdown transparent
   and paint underneath the following rows. */
.side-row--muted > span:first-child {
    opacity: 0.7;
}
.side-row--idle > button,
.side-row--idle > .badge {
    opacity: 0.6;
}
.side-row--idle.active > button,
.side-row--idle.active > .badge {
    opacity: 1;
}
.rss-list {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.rss-row__favicon {
    width: 16px;
    height: 16px;
    object-fit: contain;
}
.rss-detail {
    background: var(--bs-body-bg);
}
.rss-excerpt {
    line-height: 1.55;
    color: var(--bs-body-color);
}
</style>
