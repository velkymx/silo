<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { fmtBytes } from '../../lib/format';
import { histogramFromUrl, type Histogram } from '../../lib/histogram';

interface PhotoMeta {
    lens?: string;
    exposure?: string;
    aperture?: string;
    iso?: number | string;
    focal_length?: string;
    gps?: { lat: number; lng: number };
    iptc?: {
        title?: string;
        caption?: string;
        keywords?: string[];
        credit?: string;
        copyright?: string;
        city?: string;
        country?: string;
    };
    xmp?: {
        title?: string;
        description?: string;
        creator?: string;
        subjects?: string[];
    };
}

interface PhotoShape {
    id: number;
    name: string;
    url: string;
    size: number | null;
    width: number | null;
    height: number | null;
    taken_at: number;
    camera: string | null;
    photo_meta: PhotoMeta | null;
}

const props = defineProps<{ photo: PhotoShape }>();

const meta = computed<PhotoMeta>(() => props.photo.photo_meta ?? {});

// Descriptive fields: IPTC wins, XMP fills the gaps.
const description = computed(() => ({
    title: meta.value.iptc?.title ?? meta.value.xmp?.title ?? null,
    caption: meta.value.iptc?.caption ?? meta.value.xmp?.description ?? null,
    keywords: meta.value.iptc?.keywords ?? meta.value.xmp?.subjects ?? [],
    credit: meta.value.iptc?.credit ?? meta.value.xmp?.creator ?? null,
    copyright: meta.value.iptc?.copyright ?? null,
    place: [meta.value.iptc?.city, meta.value.iptc?.country].filter(Boolean).join(', ') || null,
}));

const hasCamera = computed(() =>
    !!(props.photo.camera || meta.value.exposure || meta.value.aperture || meta.value.iso || meta.value.focal_length || meta.value.lens));
const hasDescription = computed(() =>
    !!(description.value.title || description.value.caption || description.value.keywords.length
        || description.value.credit || description.value.copyright || description.value.place));

const osmUrl = computed(() => {
    const gps = meta.value.gps;
    return gps ? `https://www.openstreetmap.org/?mlat=${gps.lat}&mlon=${gps.lng}#map=15/${gps.lat}/${gps.lng}` : null;
});

// ---- Histogram (computed client-side from the displayed image) ----
const histogram = ref<Histogram | null>(null);
const histogramFailed = ref(false);

async function loadHistogram(): Promise<void> {
    histogram.value = null;
    histogramFailed.value = false;
    try {
        histogram.value = await histogramFromUrl(props.photo.url);
    } catch {
        histogramFailed.value = true;
    }
}
watch(() => props.photo.id, loadHistogram, { immediate: true });

const chartData = computed(() => histogram.value && ({
    labels: Array(256).fill(''),
    datasets: [
        { label: 'R', data: histogram.value.r, color: 'rgba(220, 53, 69, 0.65)' },
        { label: 'G', data: histogram.value.g, color: 'rgba(25, 135, 84, 0.65)' },
        { label: 'B', data: histogram.value.b, color: 'rgba(13, 110, 253, 0.65)' },
        { label: 'Luma', data: histogram.value.luma, color: 'rgba(150, 150, 150, 0.9)' },
    ],
}));

const takenLabel = computed(() => new Date(props.photo.taken_at * 1000).toLocaleString());
</script>

<template>
    <div class="photo-info p-3" data-testid="photo-info">
        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-3">
                <h3 class="h6 text-muted text-uppercase fw-semibold">Details</h3>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Taken</dt><dd class="col-7">{{ takenLabel }}</dd>
                    <template v-if="photo.width && photo.height">
                        <dt class="col-5 text-muted">Dimensions</dt><dd class="col-7">{{ photo.width }} × {{ photo.height }}</dd>
                    </template>
                    <template v-if="photo.size">
                        <dt class="col-5 text-muted">Size</dt><dd class="col-7">{{ fmtBytes(photo.size) }}</dd>
                    </template>
                </dl>
            </div>

            <div v-if="hasCamera" class="col-12 col-md-6 col-lg-3" data-testid="photo-info-camera">
                <h3 class="h6 text-muted text-uppercase fw-semibold">Camera</h3>
                <dl class="row small mb-0">
                    <template v-if="photo.camera"><dt class="col-5 text-muted">Camera</dt><dd class="col-7">{{ photo.camera }}</dd></template>
                    <template v-if="meta.lens"><dt class="col-5 text-muted">Lens</dt><dd class="col-7">{{ meta.lens }}</dd></template>
                    <template v-if="meta.exposure"><dt class="col-5 text-muted">Exposure</dt><dd class="col-7">{{ meta.exposure }} s</dd></template>
                    <template v-if="meta.aperture"><dt class="col-5 text-muted">Aperture</dt><dd class="col-7">{{ meta.aperture }}</dd></template>
                    <template v-if="meta.iso"><dt class="col-5 text-muted">ISO</dt><dd class="col-7">{{ meta.iso }}</dd></template>
                    <template v-if="meta.focal_length"><dt class="col-5 text-muted">Focal length</dt><dd class="col-7">{{ meta.focal_length }}</dd></template>
                </dl>
            </div>

            <div v-if="hasDescription || osmUrl" class="col-12 col-md-6 col-lg-3" data-testid="photo-info-description">
                <h3 class="h6 text-muted text-uppercase fw-semibold">Description</h3>
                <dl class="row small mb-2">
                    <template v-if="description.title"><dt class="col-5 text-muted">Title</dt><dd class="col-7">{{ description.title }}</dd></template>
                    <template v-if="description.caption"><dt class="col-5 text-muted">Caption</dt><dd class="col-7">{{ description.caption }}</dd></template>
                    <template v-if="description.credit"><dt class="col-5 text-muted">Credit</dt><dd class="col-7">{{ description.credit }}</dd></template>
                    <template v-if="description.copyright"><dt class="col-5 text-muted">Copyright</dt><dd class="col-7">{{ description.copyright }}</dd></template>
                    <template v-if="description.place"><dt class="col-5 text-muted">Place</dt><dd class="col-7">{{ description.place }}</dd></template>
                    <template v-if="osmUrl">
                        <dt class="col-5 text-muted">Location</dt>
                        <dd class="col-7">
                            <a :href="osmUrl" target="_blank" rel="noopener" data-testid="photo-info-gps">
                                {{ meta.gps!.lat }}, {{ meta.gps!.lng }}
                            </a>
                        </dd>
                    </template>
                </dl>
                <span v-for="kw in description.keywords" :key="kw" class="badge text-bg-light me-1 mb-1">{{ kw }}</span>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <h3 class="h6 text-muted text-uppercase fw-semibold">Histogram</h3>
                <VibeChartLine
                    v-if="chartData"
                    :data="chartData"
                    legend="none"
                    :show-axes="false"
                    :show-grid="false"
                    :height="110"
                />
                <p v-else-if="histogramFailed" class="small text-muted mb-0">Not available for this image.</p>
                <p v-else class="small text-muted mb-0">Computing…</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.photo-info {
    background: var(--bs-tertiary-bg);
    border-top: 1px solid var(--bs-border-color);
}
</style>
