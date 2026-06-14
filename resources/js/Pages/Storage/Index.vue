<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({ used: 0, quota: 0 }) },
    nodes: { type: Array, default: () => [] },
    largest: { type: Array, default: () => [] },
    byCategory: { type: Object, default: () => ({}) },
});

const CATEGORY_COLORS = {
    image: '#10b981',
    video: '#6366f1',
    audio: '#ec4899',
    pdf: '#ef4444',
    document: '#3b82f6',
    spreadsheet: '#22c55e',
    archive: '#f59e0b',
    folder: '#64748b',
    other: '#94a3b8',
};
const colorFor = (c) => CATEGORY_COLORS[c] || CATEGORY_COLORS.other;

function fmtBytes(n) {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(i ? 1 : 0)} ${u[i]}`;
}

// ----- Drill-down state -----
const currentId = ref(null);
const byId = computed(() => Object.fromEntries(props.nodes.map((n) => [n.id, n])));
const path = computed(() => {
    const trail = [];
    let id = currentId.value;
    while (id) {
        const n = byId.value[id];
        if (!n) break;
        trail.unshift(n);
        id = n.parent_id;
    }
    return trail;
});

const levelItems = computed(() =>
    props.nodes
        .filter((n) => (n.parent_id ?? null) === currentId.value && n.size > 0)
        .sort((a, b) => b.size - a.size)
);

function enter(node) {
    if (node.is_dir) currentId.value = node.id;
    else router.get('/', node.parent_id ? { folder: node.parent_id } : {});
}

// ----- Squarified treemap layout -----
const box = ref(null);
const dims = ref({ w: 800, h: 460 });
let ro = null;
onMounted(() => {
    const measure = () => {
        if (box.value) dims.value = { w: box.value.clientWidth, h: box.value.clientHeight };
    };
    measure();
    ro = new ResizeObserver(measure);
    if (box.value) ro.observe(box.value);
});
onBeforeUnmount(() => ro?.disconnect());

function worst(row, length, scale) {
    const areas = row.map((r) => r.size * scale);
    const sum = areas.reduce((a, b) => a + b, 0);
    const max = Math.max(...areas);
    const min = Math.min(...areas);
    return Math.max((length * length * max) / (sum * sum), (sum * sum) / (length * length * min));
}

const rects = computed(() => {
    const items = levelItems.value;
    const { w, h } = dims.value;
    const total = items.reduce((a, b) => a + b.size, 0);
    if (!total || w < 10 || h < 10) return [];
    const scale = (w * h) / total;

    const out = [];
    let x = 0;
    let y = 0;
    let rw = w;
    let rh = h;
    let i = 0;
    let row = [];

    const layoutRow = () => {
        const areas = row.map((r) => r.size * scale);
        const sum = areas.reduce((a, b) => a + b, 0);
        const vertical = rw >= rh;
        const thickness = sum / (vertical ? rh : rw);
        let off = vertical ? y : x;
        row.forEach((r, k) => {
            const len = areas[k] / thickness;
            if (vertical) {
                out.push({ node: r, x, y: off, w: thickness, h: len });
            } else {
                out.push({ node: r, x: off, y, w: len, h: thickness });
            }
            off += len;
        });
        if (vertical) { x += thickness; rw -= thickness; } else { y += thickness; rh -= thickness; }
    };

    while (i < items.length) {
        const next = items[i];
        const length = Math.min(rw, rh);
        if (row.length === 0) { row.push(next); i++; continue; }
        const cur = worst(row, length, scale);
        const withNext = worst([...row, next], length, scale);
        if (withNext <= cur) { row.push(next); i++; } else { layoutRow(); row = []; }
    }
    if (row.length) layoutRow();
    return out;
});

const hover = ref(null);
const categories = computed(() => Object.entries(props.byCategory).map(([k, v]) => ({ name: k, size: v })));
const pct = computed(() => (props.summary.quota > 0 ? Math.min(100, Math.round((props.summary.used / props.summary.quota) * 100)) : 0));
</script>

<template>
    <AppLayout>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h4 class="mb-0"><VibeIcon icon="hdd-fill" class="me-2" />Storage</h4>
            <span class="text-muted">
                {{ fmtBytes(summary.used) }}<template v-if="summary.quota > 0"> of {{ fmtBytes(summary.quota) }} ({{ pct }}%)</template> used
            </span>
        </div>

        <!-- Drill path -->
        <div class="d-flex align-items-center gap-1 mb-2 small">
            <VibeButton variant="link" class="p-0 text-decoration-none" @click="currentId = null">
                <VibeIcon icon="house-door-fill" class="me-1" />Home
            </VibeButton>
            <template v-for="n in path" :key="n.id">
                <VibeIcon icon="chevron-right" class="text-muted" style="font-size: 0.7rem" />
                <VibeButton variant="link" class="p-0 text-decoration-none" @click="currentId = n.id">{{ n.name }}</VibeButton>
            </template>
        </div>

        <VibeRow class="g-3">
            <VibeCol :lg="8">
                <div ref="box" class="treemap-box border rounded">
                    <div
                        v-for="r in rects"
                        :key="r.node.id"
                        class="treemap-tile"
                        :style="{
                            left: r.x + 'px', top: r.y + 'px', width: r.w + 'px', height: r.h + 'px',
                            background: colorFor(r.node.category),
                        }"
                        :title="`${r.node.name} — ${fmtBytes(r.node.size)}`"
                        @click="enter(r.node)"
                        @mouseenter="hover = r.node"
                        @mouseleave="hover = null"
                    >
                        <span v-if="r.w > 54 && r.h > 22" class="tile-label">
                            <VibeIcon v-if="r.node.is_dir" icon="folder-fill" class="me-1" />{{ r.node.name }}
                        </span>
                    </div>
                    <div v-if="!rects.length" class="text-muted d-flex align-items-center justify-content-center h-100">
                        Nothing stored here yet.
                    </div>
                </div>
                <div class="small text-muted mt-1" style="height: 1.2rem">
                    <template v-if="hover"><strong>{{ hover.name }}</strong> · {{ fmtBytes(hover.size) }} · {{ hover.category }}</template>
                    <template v-else>Click a folder tile to drill in. Hover for details.</template>
                </div>
            </VibeCol>

            <VibeCol :lg="4">
                <VibeCard header="By type" class="mb-3">
                    <div v-for="c in categories" :key="c.name" class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><span class="legend-dot" :style="{ background: colorFor(c.name) }"></span>{{ c.name }}</span>
                            <span class="text-muted">{{ fmtBytes(c.size) }}</span>
                        </div>
                        <div class="type-bar">
                            <div :style="{ width: (summary.used ? (c.size / summary.used * 100) : 0) + '%', background: colorFor(c.name) }"></div>
                        </div>
                    </div>
                    <p v-if="!categories.length" class="text-muted small mb-0">No files yet.</p>
                </VibeCard>

                <VibeCard header="Largest files">
                    <div v-for="f in largest" :key="f.id" class="d-flex align-items-center gap-2 py-1 border-bottom">
                        <span class="legend-dot" :style="{ background: colorFor(f.category) }"></span>
                        <span class="text-truncate flex-grow-1 small">{{ f.name }}</span>
                        <span class="text-muted small text-nowrap">{{ fmtBytes(f.size) }}</span>
                    </div>
                    <p v-if="!largest.length" class="text-muted small mb-0">No files yet.</p>
                </VibeCard>
            </VibeCol>
        </VibeRow>
    </AppLayout>
</template>

<style scoped>
.treemap-box {
    position: relative;
    width: 100%;
    height: 62vh;
    overflow: hidden;
    background: var(--bs-tertiary-bg);
}
.treemap-tile {
    position: absolute;
    border: 1px solid rgba(255, 255, 255, 0.5);
    cursor: pointer;
    overflow: hidden;
    transition: filter 0.1s;
}
.treemap-tile:hover {
    filter: brightness(1.12);
    z-index: 2;
}
.tile-label {
    color: #fff;
    font-size: 0.72rem;
    padding: 2px 4px;
    display: block;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
}
.legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 6px;
}
.type-bar {
    height: 6px;
    border-radius: 3px;
    background: var(--bs-secondary-bg);
    overflow: hidden;
}
.type-bar > div {
    height: 100%;
}
</style>
