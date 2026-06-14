<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const props = defineProps({
    photos: { type: Array, default: () => [] },
    albums: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ album: null, tag: null }) },
});

// ----- Timeline grouping (by month) -----
const groups = computed(() => {
    const out = [];
    const byKey = {};
    for (const p of props.photos) {
        if (!byKey[p.date]) {
            byKey[p.date] = { key: p.date, label: p.date_label, items: [] };
            out.push(byKey[p.date]);
        }
        byKey[p.date].items.push(p);
    }
    return out;
});

// ----- Filters -----
function applyFilter(patch) {
    const q = { album: props.filters.album, tag: props.filters.tag, ...patch };
    router.get('/photos', Object.fromEntries(Object.entries(q).filter(([, v]) => v)), {
        preserveScroll: true, preserveState: true,
    });
}
const activeAlbum = computed(() => props.albums.find((a) => a.id === props.filters.album));

// ----- Selection -----
const selectMode = ref(false);
const selected = ref(new Set());
function toggleSelect(id) {
    selected.value.has(id) ? selected.value.delete(id) : selected.value.add(id);
    selected.value = new Set(selected.value);
}
function clearSelection() {
    selected.value = new Set();
    selectMode.value = false;
}

// ----- Lightbox -----
const lightboxOpen = ref(false);
const slide = ref(0);
const ride = ref(false);
const carouselItems = computed(() => props.photos.map((p) => ({ src: p.url, alt: p.name })));
const currentPhoto = computed(() => props.photos[slide.value] || null);

function openPhoto(p) {
    if (selectMode.value) { toggleSelect(p.id); return; }
    slide.value = props.photos.findIndex((x) => x.id === p.id);
    lightboxOpen.value = true;
}

function star(p) {
    router.post(`/files/${p.id}/star`, {}, { preserveScroll: true, preserveState: false });
}
function destroyPhoto(p) {
    if (!confirm(`Move “${p.name}” to trash?`)) return;
    router.delete(`/delete/${p.id}`, { preserveScroll: true, onSuccess: () => { lightboxOpen.value = false; } });
}

// ----- Upload -----
const uploadOpen = ref(false);
const uploadForm = useForm({ files: [] });
function submitUpload() {
    uploadForm.post('/photos/upload', { onSuccess: () => { uploadOpen.value = false; uploadForm.reset(); } });
}

// ----- Albums -----
const albumOpen = ref(false);
const albumForm = useForm({ name: '' });
function createAlbum() {
    albumForm.post('/photos/albums', { onSuccess: () => { albumOpen.value = false; albumForm.reset(); } });
}
function addSelectedToAlbum(albumId) {
    router.post(`/photos/albums/${albumId}/photos`, { file_ids: [...selected.value] }, {
        preserveScroll: true, onSuccess: clearSelection,
    });
}
function deleteAlbum(a) {
    if (confirm(`Delete album “${a.name}”? Photos are kept.`)) {
        router.delete(`/photos/albums/${a.id}`, { preserveScroll: true });
    }
}

// ----- Editor (vue-advanced-cropper) -----
const editorOpen = ref(false);
const editorPhoto = ref(null);
const cropper = ref(null);
const editorSaving = ref(false);
function openEditor(p) {
    editorPhoto.value = p;
    editorOpen.value = true;
}
function rotate(deg) { cropper.value?.rotate(deg); }
function flip(h, v) { cropper.value?.flip(h, v); }
function saveEdit() {
    const { canvas } = cropper.value.getResult();
    if (!canvas) return;
    editorSaving.value = true;
    canvas.toBlob((blob) => {
        const form = new FormData();
        form.append('file', new File([blob], editorPhoto.value.name));
        form.append('note', 'Photo edit');
        form.append('_method', 'put');
        router.post(`/files/${editorPhoto.value.id}/content`, form, {
            forceFormData: true,
            onSuccess: () => { editorOpen.value = false; lightboxOpen.value = false; },
            onFinish: () => { editorSaving.value = false; },
        });
    }, 'image/jpeg', 0.92);
}
</script>

<template>
    <AppLayout>
        <!-- Toolbar -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h4 class="mb-0 me-2"><VibeIcon icon="images" class="me-2" />Photos</h4>

            <VibeFormSelect
                :model-value="filters.album || ''"
                :options="[{ value: '', text: 'All albums' }, ...albums.map((a) => ({ value: a.id, text: a.name }))]"
                style="max-width: 180px"
                @update:model-value="applyFilter({ album: $event || null })"
            />
            <VibeFormSelect
                v-if="tags.length"
                :model-value="filters.tag || ''"
                :options="[{ value: '', text: 'All tags' }, ...tags.map((t) => ({ value: t.id, text: t.name }))]"
                style="max-width: 160px"
                @update:model-value="applyFilter({ tag: $event || null })"
            />

            <div class="ms-auto d-flex gap-2">
                <VibeButton :variant="selectMode ? 'primary' : 'secondary'" outline @click="selectMode = !selectMode; selected = new Set()">
                    <VibeIcon icon="check2-square" class="me-1" />Select
                </VibeButton>
                <VibeButton variant="secondary" outline @click="albumOpen = true">
                    <VibeIcon icon="collection" class="me-1" />New Album
                </VibeButton>
                <VibeButton variant="primary" @click="uploadOpen = true">
                    <VibeIcon icon="upload" class="me-1" />Upload
                </VibeButton>
            </div>
        </div>

        <!-- Albums strip -->
        <div v-if="albums.length && !filters.album" class="d-flex gap-3 overflow-auto pb-2 mb-3">
            <div v-for="a in albums" :key="a.id" class="text-center" style="cursor: pointer; min-width: 120px" @click="applyFilter({ album: a.id })">
                <div class="rounded border bg-body-tertiary d-flex align-items-center justify-content-center overflow-hidden" style="width: 120px; height: 120px">
                    <img v-if="a.cover_url" :src="a.cover_url" :alt="a.name" style="width: 100%; height: 100%; object-fit: cover">
                    <VibeIcon v-else icon="collection" class="display-5 text-muted" />
                </div>
                <div class="small fw-medium text-truncate mt-1" style="width: 120px">{{ a.name }}</div>
                <div class="text-muted" style="font-size: 0.72rem">{{ a.count }} photos</div>
            </div>
        </div>

        <!-- Active album header -->
        <div v-if="activeAlbum" class="d-flex align-items-center gap-2 mb-3">
            <VibeButton variant="link" class="p-0 text-decoration-none" @click="applyFilter({ album: null })">
                <VibeIcon icon="arrow-left" class="me-1" />All photos
            </VibeButton>
            <span class="fw-semibold">{{ activeAlbum.name }}</span>
            <VibeButton variant="danger" size="sm" outline class="ms-2" @click="deleteAlbum(activeAlbum)">
                <VibeIcon icon="trash" />
            </VibeButton>
        </div>

        <!-- Selection action bar -->
        <VibeAlert v-if="selectMode && selected.size" variant="primary" class="d-flex flex-wrap align-items-center gap-2">
            <strong>{{ selected.size }} selected</strong>
            <VibeDropdown variant="primary" size="sm" :items="albums.map((a) => ({ text: a.name, id: a.id }))" @item-click="addSelectedToAlbum($event.item.id)">
                <VibeIcon icon="collection" class="me-1" />Add to album
            </VibeDropdown>
            <VibeButton variant="secondary" size="sm" outline @click="clearSelection">Clear</VibeButton>
        </VibeAlert>

        <!-- Timeline -->
        <p v-if="!photos.length" class="text-muted text-center py-5">No photos yet. Upload some to get started.</p>

        <div v-for="g in groups" :key="g.key" class="mb-4">
            <h6 class="text-muted border-bottom pb-1 mb-2">{{ g.label }}</h6>
            <div class="photo-grid">
                <div
                    v-for="p in g.items"
                    :key="p.id"
                    class="photo-cell"
                    :class="{ selected: selected.has(p.id) }"
                    @click="openPhoto(p)"
                >
                    <img :src="p.thumb_url" :alt="p.name" loading="lazy">
                    <VibeIcon v-if="p.starred" icon="star-fill" class="badge-star text-warning" />
                    <VibeIcon v-if="selectMode" :icon="selected.has(p.id) ? 'check-circle-fill' : 'circle'" class="badge-select" />
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <VibeModal v-model="lightboxOpen" fullscreen hide-footer>
            <template #header>
                <div class="d-flex align-items-center w-100">
                    <span class="text-truncate">{{ currentPhoto?.name }}</span>
                    <div class="ms-auto d-flex gap-2">
                        <VibeButton variant="secondary" size="sm" outline :title="ride ? 'Stop' : 'Slideshow'" @click="ride = !ride">
                            <VibeIcon :icon="ride ? 'pause-fill' : 'play-fill'" />
                        </VibeButton>
                        <VibeButton variant="secondary" size="sm" outline title="Star" @click="star(currentPhoto)">
                            <VibeIcon :icon="currentPhoto?.starred ? 'star-fill' : 'star'" />
                        </VibeButton>
                        <VibeButton variant="secondary" size="sm" outline title="Edit" @click="openEditor(currentPhoto)">
                            <VibeIcon icon="pencil" />
                        </VibeButton>
                        <VibeButton variant="success" size="sm" :href="`/download/${currentPhoto?.id}`" title="Download">
                            <VibeIcon icon="download" />
                        </VibeButton>
                        <VibeButton variant="danger" size="sm" outline title="Delete" @click="destroyPhoto(currentPhoto)">
                            <VibeIcon icon="trash" />
                        </VibeButton>
                    </div>
                </div>
            </template>
            <VibeCarousel
                v-if="lightboxOpen"
                v-model="slide"
                :items="carouselItems"
                :ride="ride ? 'carousel' : false"
                :interval="ride ? 4000 : false"
                dark
            />
        </VibeModal>

        <!-- Upload -->
        <VibeModal v-model="uploadOpen" title="Upload Photos" fullscreen>
            <VibeFileInput v-model="uploadForm.files" label="Choose images" multiple drag-drop accept="image/*" />
            <p v-if="uploadForm.errors['files.0']" class="text-danger small mt-1">{{ uploadForm.errors['files.0'] }}</p>
            <template #footer>
                <VibeButton variant="secondary" outline @click="uploadOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="uploadForm.processing || !uploadForm.files.length" @click="submitUpload">Upload</VibeButton>
            </template>
        </VibeModal>

        <!-- New album -->
        <VibeModal v-model="albumOpen" title="New Album" fullscreen>
            <VibeFormGroup label="Album name">
                <VibeFormInput v-model="albumForm.name" placeholder="Summer 2026" />
            </VibeFormGroup>
            <template #footer>
                <VibeButton variant="secondary" outline @click="albumOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="albumForm.processing || !albumForm.name" @click="createAlbum">Create</VibeButton>
            </template>
        </VibeModal>

        <!-- Photo editor -->
        <VibeModal v-model="editorOpen" :title="`Edit — ${editorPhoto?.name || ''}`" fullscreen hide-footer>
            <div class="d-flex gap-2 mb-2 flex-wrap">
                <VibeButton variant="secondary" outline size="sm" @click="rotate(-90)"><VibeIcon icon="arrow-counterclockwise" /> Left</VibeButton>
                <VibeButton variant="secondary" outline size="sm" @click="rotate(90)"><VibeIcon icon="arrow-clockwise" /> Right</VibeButton>
                <VibeButton variant="secondary" outline size="sm" @click="flip(true, false)"><VibeIcon icon="symmetry-vertical" /> Flip H</VibeButton>
                <VibeButton variant="secondary" outline size="sm" @click="flip(false, true)"><VibeIcon icon="symmetry-horizontal" /> Flip V</VibeButton>
                <div class="ms-auto d-flex gap-2">
                    <VibeButton variant="secondary" outline @click="editorOpen = false">Cancel</VibeButton>
                    <VibeButton variant="primary" :disabled="editorSaving" @click="saveEdit">
                        <VibeSpinner v-if="editorSaving" size="sm" class="me-1" />Save as new version
                    </VibeButton>
                </div>
            </div>
            <Cropper
                v-if="editorOpen"
                ref="cropper"
                :src="editorPhoto?.url"
                class="bg-dark"
                style="height: calc(100vh - 180px)"
            />
        </VibeModal>
    </AppLayout>
</template>

<style scoped>
.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 6px;
}
.photo-cell {
    position: relative;
    aspect-ratio: 1;
    cursor: pointer;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bs-tertiary-bg);
}
.photo-cell img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.15s;
}
.photo-cell:hover img {
    transform: scale(1.04);
}
.photo-cell.selected {
    outline: 3px solid var(--bs-primary);
    outline-offset: -3px;
}
.badge-star {
    position: absolute;
    top: 4px;
    right: 6px;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.6));
}
.badge-select {
    position: absolute;
    top: 4px;
    left: 6px;
    color: #fff;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.7));
}
</style>
