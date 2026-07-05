<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { readableTextColor } from '../../lib/contrast';
import { fmtBytes } from '../../lib/format';
import { categoryColor } from '../../lib/categoryColors';
import { usePageLoading } from '../../composables/usePageLoading';

const { loading } = usePageLoading();

const props = defineProps({
    summary: { type: Object, default: () => ({ used: 0, quota: 0 }) },
    nodes: { type: Array, default: () => [] },
    largest: { type: Array, default: () => [] },
    byCategory: { type: Object, default: () => ({}) },
});

const colorFor = (c) => categoryColor(c);


// ----- Build the hierarchy (root = parent_id null) -----
const tree = computed(() => {
    const byParent = {};
    for (const n of props.nodes) {
        if (n.size <= 0) continue;
        (byParent[n.parent_id ?? 0] ||= []).push(n);
    }
    const attach = (node) => ({
        ...node,
        children: (byParent[node.id] || []).map(attach).sort((a, b) => b.size - a.size),
    });
    return (byParent[0] || []).map(attach).sort((a, b) => b.size - a.size);
});

// ----- Squarified treemap, rendered as a full nested layout -----
const box = ref(null);
const dims = ref({ w: 800, h: 520 });
let ro = null;
let measureTimer = null;
onMounted(() => {
    const apply = () => { if (box.value) dims.value = { w: box.value.clientWidth, h: box.value.clientHeight }; };
    apply();
    // Debounce: a drag-resize fires dozens of events; recompute the (heavy)
    // treemap only once the size settles.
    const measure = () => {
        if (measureTimer) clearTimeout(measureTimer);
        measureTimer = setTimeout(apply, 120);
    };
    ro = new ResizeObserver(measure);
    if (box.value) ro.observe(box.value);
});
onBeforeUnmount(() => {
    ro?.disconnect();
    if (measureTimer) clearTimeout(measureTimer);
});

function worst(areas, length) {
    const sum = areas.reduce((a, b) => a + b, 0);
    const max = Math.max(...areas), min = Math.min(...areas);
    return Math.max((length * length * max) / (sum * sum), (sum * sum) / (length * length * min));
}

// Lay out one set of items inside a rect; returns [{node,x,y,w,h}].
function squarify(items, x, y, w, h) {
    const total = items.reduce((a, b) => a + b.size, 0);
    if (total <= 0 || w < 1 || h < 1) return [];
    const scale = (w * h) / total;
    const out = [];
    let rx = x, ry = y, rw = w, rh = h, i = 0, row = [];
    const flush = () => {
        const areas = row.map((r) => r.size * scale);
        const sum = areas.reduce((a, b) => a + b, 0);
        const vertical = rw >= rh;
        const thick = sum / (vertical ? rh : rw);
        let off = vertical ? ry : rx;
        row.forEach((r, k) => {
            const len = areas[k] / thick;
            out.push(vertical
                ? { node: r, x: rx, y: off, w: thick, h: len }
                : { node: r, x: off, y: ry, w: len, h: thick });
            off += len;
        });
        if (vertical) { rx += thick; rw -= thick; } else { ry += thick; rh -= thick; }
    };
    while (i < items.length) {
        const length = Math.min(rw, rh);
        if (!row.length) { row.push(items[i++]); continue; }
        const cur = worst(row.map((r) => r.size * scale), length);
        const nxt = worst([...row, items[i]].map((r) => r.size * scale), length);
        if (nxt <= cur) { row.push(items[i++]); } else { flush(); row = []; }
    }
    if (row.length) flush();
    return out;
}

const HEADER = 15;
const PAD = 2;
const MAX_TILES = 6000;

const treemap = computed(() => {
    const { w, h } = dims.value;
    const out = [];
    let truncated = false;
    const layout = (items, x, y, rw, rh, depth) => {
        if (out.length >= MAX_TILES || rw < 3 || rh < 3) return;
        for (const r of squarify(items, x, y, rw, rh)) {
            if (out.length >= MAX_TILES) { truncated = true; return; }
            const isFolder = r.node.is_dir && r.node.children?.length;
            out.push({ ...r, isFolder: !!isFolder, node: r.node, depth });
            if (isFolder && r.w > 24 && r.h > HEADER + 8) {
                layout(
                    r.node.children,
                    r.x + PAD, r.y + PAD + HEADER,
                    r.w - 2 * PAD, r.h - 2 * PAD - HEADER,
                    depth + 1,
                );
            }
        }
    };
    if (w > 10 && h > 10) layout(tree.value, 0, 0, w, h, 0);
    return { tiles: out, truncated };
});

const tiles = computed(() => treemap.value.tiles);
const truncated = computed(() => treemap.value.truncated);

function open(node) {
    if (node.is_dir) router.get('/', { folder: node.id });
    else router.get('/', node.parent_id ? { folder: node.parent_id } : {});
}

const hover = ref(null);
const categories = computed(() => Object.entries(props.byCategory).map(([k, v]) => ({ name: k, size: v })));
const pct = computed(() => (props.summary.quota > 0 ? Math.min(100, Math.round((props.summary.used / props.summary.quota) * 100)) : 0));
</script>

<template>
    <ShellLayout :folders-visible="false" :detail-visible="false">
        <!-- Breadcrumb + usage summary + hover readout on one top-bar line. -->
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-1">
                <VibeBreadcrumb :items="[{ text: 'Storage', active: true }]" class="breadcrumb mb-0 pb-0 text-truncate min-w-0">
                    <template #item="{ item }">
                        <VibeIcon icon="hdd-fill" class="me-1" /><span>{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <span class="text-muted small">
                    {{ fmtBytes(summary.used) }}<template v-if="summary.quota > 0"> of {{ fmtBytes(summary.quota) }} ({{ pct }}%)</template> used
                </span>
                <span class="ms-auto small text-muted text-truncate" style="min-height: 1.2rem">
                    <template v-if="hover"><strong>{{ hover.name }}</strong> · {{ fmtBytes(hover.size) }}<span v-if="!hover.is_dir"> · {{ hover.category }}</span></template>
                    <template v-else>Every file as a tile, nested inside its folder. Click to open.</template>
                </span>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1 p-3">
                <LoadingSkeleton v-if="loading" :rows="8" :cols="4" />
                <VibeAlert v-if="truncated && !loading" variant="info" class="py-2 small">
                    <VibeIcon icon="info-circle" class="me-1" />Showing the {{ tiles.length.toLocaleString() }} largest areas. Some smaller items are hidden — open a folder to drill in.
                </VibeAlert>
                <VibeRow v-show="!loading" class="g-3">
            <VibeCol :lg="8">
                <div ref="box" class="treemap-box border rounded">
                    <template v-for="t in tiles" :key="t.node.id">
                        <!-- Folder frame -->
                        <div
                            v-if="t.isFolder"
                            class="tm-folder"
                            :style="{ left: t.x + 'px', top: t.y + 'px', width: t.w + 'px', height: t.h + 'px', zIndex: t.depth }"
                            @click.stop="open(t.node)"
                            @mouseenter="hover = t.node"
                        >
                            <div v-if="t.w > 40" class="tm-folder-label"><VibeIcon icon="folder-fill" class="me-1" />{{ t.node.name }}</div>
                        </div>
                        <!-- File tile -->
                        <div
                            v-else
                            class="tm-file"
                            :style="{ left: t.x + 'px', top: t.y + 'px', width: t.w + 'px', height: t.h + 'px', background: colorFor(t.node.category), zIndex: 100 + t.depth }"
                            :title="`${t.node.name} — ${fmtBytes(t.node.size)}`"
                            @click.stop="open(t.node)"
                            @mouseenter="hover = t.node"
                        >
                            <span
                                v-if="t.w > 50 && t.h > 20"
                                class="tm-file-label"
                                :style="{ color: readableTextColor(colorFor(t.node.category)) }"
                            >{{ t.node.name }}</span>
                        </div>
                    </template>
                    <div v-if="!tiles.length" class="text-muted d-flex align-items-center justify-content-center h-100">
                        Nothing stored yet.
                    </div>
                </div>
            </VibeCol>

            <VibeCol :lg="4">
                <VibeCard header="By type" class="mb-3">
                    <div v-for="c in categories" :key="c.name" class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><span class="legend-dot" :style="{ background: colorFor(c.name) }"></span>{{ c.name }}</span>
                            <span class="text-muted">{{ fmtBytes(c.size) }}</span>
                        </div>
                        <div class="type-bar"><div :style="{ width: (summary.used ? (c.size / summary.used * 100) : 0) + '%', background: colorFor(c.name) }"></div></div>
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
            </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.treemap-box {
    position: relative;
    width: 100%;
    height: 64vh;
    overflow: hidden;
    background: var(--bs-tertiary-bg);
}
.tm-folder {
    position: absolute;
    border: 1px solid var(--bs-border-color);
    background: color-mix(in srgb, var(--bs-secondary-bg) 70%, transparent);
    cursor: pointer;
}
.tm-folder-label {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 1px 4px;
    color: var(--bs-body-color);
    white-space: nowrap;
    overflow: hidden;
    background: var(--bs-secondary-bg);
    border-bottom: 1px solid var(--bs-border-color);
}
.tm-file {
    position: absolute;
    border: 1px solid rgba(255, 255, 255, 0.45);
    cursor: pointer;
    overflow: hidden;
}
.tm-file:hover {
    filter: brightness(1.15);
}
.tm-file-label {
    font-size: 0.68rem;
    padding: 1px 3px;
    display: block;
    white-space: nowrap;
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
