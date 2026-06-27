<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { triggerDownload } from '../../lib/download';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { useQuickLook } from '../../composables/useQuickLook';
import QuickLookModal from '../../Components/QuickLookModal.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { usePageLoading } from '../../composables/usePageLoading';
import ResourceModal from '../../Components/ResourceModal.vue';

const { confirm } = useConfirm();
const toast = useToast();
const { loading } = usePageLoading();

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
async function batchDeleteSelected() {
    if (!await confirm({ title: 'Move to trash', message: `Move ${selected.value.size} photo(s) to trash?`, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    router.post('/files/batch/delete', { ids: [...selected.value] }, {
        preserveScroll: true, onSuccess: clearSelection,
    });
}

// ----- Lightbox -----
const photosRef = computed(() => props.photos);
const { quickOpen, quickIndex, quickFile, step, close: qlClose } = useQuickLook(photosRef);

const prevPhoto = computed(() => props.photos[quickIndex.value - 1] ?? null);
const nextPhoto = computed(() => props.photos[quickIndex.value + 1] ?? null);
const lightboxMenu = computed(() => {
    if (!quickFile.value) return [];
    return [
        { text: isStarred(quickFile.value) ? 'Unstar' : 'Star', action: 'star', icon: isStarred(quickFile.value) ? 'star-fill' : 'star' },
        { text: 'Edit', action: 'edit', icon: 'pencil' },
        { text: 'Delete', action: 'delete', icon: 'trash' },
    ];
});
function onLightboxAction({ item }) {
    const p = quickFile.value;
    if (!p) return;
    if (item.action === 'star') star(p);
    if (item.action === 'edit') openEditor(p);
    if (item.action === 'delete') destroyPhoto(p);
}

function openPhoto(p) {
    if (selectMode.value) { toggleSelect(p.id); return; }
    const idx = props.photos.findIndex((x) => x.id === p.id);
    quickIndex.value = idx >= 0 ? idx : 0;
    quickOpen.value = true;
}

// Drag-to-reorder the filmstrip.
const filmPhotos = ref([...props.photos]);
const filmDirty = ref(false);
watch(() => props.photos, (next) => {
    // Ignore prop updates while a reorder save is in flight to avoid
    // blowing away the user's drag order before the server confirms.
    if (!filmDirty.value) filmPhotos.value = [...next];
});

function onFilmstripReorder() {
    filmDirty.value = true;
    const currentId = quickFile.value?.id ?? null;
    const ids = filmPhotos.value.map((p) => p.id);
    router.post('/photos/reorder', { ids }, {
        preserveScroll: true,
        preserveState: true,
        only: ['photos'],
        onSuccess: () => {
            filmDirty.value = false;
            if (currentId !== null) {
                const idx = props.photos.findIndex((p) => p.id === currentId);
                if (idx >= 0) quickIndex.value = idx;
            }
        },
        onError: () => { filmDirty.value = false; },
    });
}

// Keyboard navigation while the lightbox is open.
function onLightboxKey(e) {
    if (!quickOpen.value) return;
    const tag = (e.target?.tagName || '').toLowerCase();
    if (['input', 'textarea', 'select'].includes(tag) || e.target?.isContentEditable) return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); step(1); }
    else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
    else if (e.key === 'Escape') qlClose();
}
onMounted(() => window.addEventListener('keydown', onLightboxKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onLightboxKey));

// Keep the active filmstrip thumbnail scrolled into view.
const filmstripEl = ref(null);
watch(quickIndex, async () => {
    await nextTick();
    const root = filmstripEl.value?.$el ?? filmstripEl.value;
    const strip = root?.querySelector?.('.filmstrip') ?? root;
    strip?.children?.[quickIndex.value]?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
});

const localStars = reactive({});
function isStarred(p) { return p ? (localStars[p.id] ?? p.starred) : false; }

function star(p) {
    if (!p) return;
    const prev = isStarred(p);
    localStars[p.id] = !prev;
    router.post(`/files/${p.id}/star`, {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => { localStars[p.id] = prev; },
        onSuccess: () => { delete localStars[p.id]; },
    });
}

function photoMenu(p) {
    return [
        { text: 'Open', action: 'open', icon: 'arrows-fullscreen' },
        { text: 'Edit', action: 'edit', icon: 'pencil' },
        { text: isStarred(p) ? 'Unstar' : 'Star', action: 'star', icon: isStarred(p) ? 'star-fill' : 'star' },
        { text: 'Download', action: 'download', icon: 'download' },
        { text: 'Delete', action: 'delete', icon: 'trash' },
    ];
}
function onPhotoMenu(p, { item }) {
    if (item.action === 'open') openPhoto(p);
    if (item.action === 'edit') openEditor(p);
    if (item.action === 'star') star(p);
    if (item.action === 'download') triggerDownload(`/download/${p.id}`);
    if (item.action === 'delete') destroyPhoto(p);
}
async function destroyPhoto(p) {
    if (!p) return;
    if (!await confirm({ title: 'Move to trash', message: `Move “${p.name}” to trash?`, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    router.delete(`/delete/${p.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            qlClose();
            toast.push(`"${p.name}" moved to trash`, {
                undo: () => router.post(`/trash/${p.id}/restore`, {}, { preserveScroll: true }),
            });
        },
    });
}

// ----- Upload -----
const uploadOpen = ref(false);
const uploadForm = useForm({ files: [] });
function submitUpload() {
    uploadForm.post('/photos/upload', { onSuccess: () => { uploadOpen.value = false; uploadForm.reset(); } });
}

// ----- Albums -----
const albumModal = ref(null);
const albumForm = useForm({ name: '' });
function openNewAlbum() {
    albumModal.value?.openAdd();
}
function addSelectedToAlbum(albumId) {
    router.post(`/photos/albums/${albumId}/photos`, { file_ids: [...selected.value] }, {
        preserveScroll: true, onSuccess: clearSelection,
    });
}
async function deleteAlbum(a) {
    if (await confirm({ title: 'Delete album', message: `Delete album “${a.name}”? Photos are kept.`, confirmLabel: 'Delete', variant: 'danger' })) {
        router.delete(`/photos/albums/${a.id}`, { preserveScroll: true });
    }
}

// ----- Editor (vue-advanced-cropper) -----
const editorOpen = ref(false);
const editorPhoto = ref(null);
const cropper = ref(null);
const editorSaving = ref(false);
function openEditor(p) {
    if (!p) return;
    editorPhoto.value = p;
    editorOpen.value = true;
}
function rotate(deg) { cropper.value?.rotate(deg); }
function flip(h, v) { cropper.value?.flip(h, v); }
function saveEdit() {
    if (!cropper.value) return;
    const canvas = cropper.value.getResult()?.canvas;
    if (!canvas) return;
    editorSaving.value = true;
    canvas.toBlob((blob) => {
        // Guard the async callback: a null blob or a closed editor must reset the
        // saving flag, otherwise the Save button stays disabled forever.
        if (!blob || !editorPhoto.value) {
            editorSaving.value = false;
            return;
        }
        const form = new FormData();
        form.append('file', new File([blob], editorPhoto.value.name));
        form.append('note', 'Photo edit');
        form.append('_method', 'put');
        router.post(`/files/${editorPhoto.value.id}/content`, form, {
            forceFormData: true,
            onSuccess: () => { editorOpen.value = false; qlClose(); },
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
                <VibeButton variant="secondary" outline @click="openNewAlbum">
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
            <VibeButton variant="danger" size="sm" outline class="ms-2" :aria-label="`Delete album ${activeAlbum.name}`" @click="deleteAlbum(activeAlbum)">
                <VibeIcon icon="trash" />
            </VibeButton>
        </div>

        <!-- Selection action bar -->
        <VibeAlert v-if="selectMode && selected.size" variant="primary" class="d-flex flex-wrap align-items-center gap-2">
            <strong>{{ selected.size }} selected</strong>
            <VibeDropdown variant="primary" size="sm" :items="albums.map((a) => ({ text: a.name, id: a.id }))" @item-click="addSelectedToAlbum($event.item.id)">
                <VibeIcon icon="collection" class="me-1" />Add to album
            </VibeDropdown>
            <VibeButton variant="danger" size="sm" outline @click="batchDeleteSelected">
                <VibeIcon icon="trash" class="me-1" />Delete
            </VibeButton>
            <VibeButton variant="secondary" size="sm" outline @click="clearSelection">Clear</VibeButton>
        </VibeAlert>

        <!-- Timeline -->
        <LoadingSkeleton v-if="loading" :rows="6" :cols="4" />
        <EmptyState v-else-if="!photos.length" icon="images" title="No photos yet" hint="Upload some to get started." />

        <div v-for="g in groups" v-show="!loading" :key="g.key" class="mb-4">
            <h6 class="text-muted border-bottom pb-1 mb-2">{{ g.label }}</h6>
            <div class="photo-grid">
                <div
                    v-for="p in g.items"
                    :key="p.id"
                    class="photo-cell"
                    :class="{ selected: selected.has(p.id) }"
                >
                    <button type="button" class="photo-thumb" :aria-label="`Open ${p.name}`" @click="openPhoto(p)">
                        <img :src="p.thumb_url" :alt="p.name" loading="lazy">
                    </button>

                    <!-- Star (click to toggle) -->
                    <VibeButton
                        variant="link"
                        class="cell-star p-0"
                        :title="isStarred(p) ? 'Unstar' : 'Star'"
                        :aria-label="(isStarred(p) ? 'Unstar ' : 'Star ') + p.name"
                        @click.stop="star(p)"
                    >
                        <VibeIcon :icon="isStarred(p) ? 'star-fill' : 'star'" :class="isStarred(p) ? 'text-warning' : 'text-white'" />
                    </VibeButton>

                    <!-- Actions -->
                    <div class="cell-actions" @click.stop>
                        <VibeDropdown size="sm" variant="light" menu-end :title="`Photo actions ${p.name}`" :items="photoMenu(p)" @item-click="onPhotoMenu(p, $event)">
                            <template #button><VibeIcon icon="three-dots-vertical" /><span class="visually-hidden">Photo actions</span></template>
                            <template #item="{ item: a }"><VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}</template>
                        </VibeDropdown>
                    </div>

                    <VibeIcon v-if="selectMode" :icon="selected.has(p.id) ? 'check-circle-fill' : 'circle'" class="badge-select" />
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <QuickLookModal
            v-model="quickOpen"
            :file="quickFile"
            :index="quickIndex"
            :total="photos.length"
            :prev-file="prevPhoto"
            :next-file="nextPhoto"
            :menu="lightboxMenu"
            @step="step($event)"
            @action="onLightboxAction($event)"
        >
            <template #below>
                <!-- Thumbnail filmstrip (drag to reorder) -->
                <VibeSortable
                    ref="filmstripEl"
                    v-model="filmPhotos"
                    item-key="id"
                    tag="div"
                    item-tag="div"
                    class="filmstrip"
                    @update:model-value="onFilmstripReorder"
                >
                    <template #default="{ item: p }">
                        <img
                            :src="p.thumb_url"
                            :alt="p.name"
                            class="film-thumb"
                            :class="{ active: quickFile && p.id === quickFile.id }"
                            @click="quickIndex = photos.findIndex((x) => x.id === p.id)"
                        >
                    </template>
                </VibeSortable>
            </template>
        </QuickLookModal>

        <!-- Upload -->
        <VibeModal v-model="uploadOpen" title="Upload Photos" size="lg" centered>
            <VibeFileInput v-model="uploadForm.files" label="Choose images" multiple drag-drop accept="image/*" />
            <p v-if="uploadForm.errors['files.0']" class="text-danger small mt-1">{{ uploadForm.errors['files.0'] }}</p>
            <template #footer>
                <VibeButton variant="secondary" outline @click="uploadOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="uploadForm.processing || !uploadForm.files.length" @click="submitUpload">Upload</VibeButton>
            </template>
        </VibeModal>

        <!-- New album -->
        <ResourceModal
            ref="albumModal"
            :form="albumForm"
            store-url="/photos/albums"
            :update-url="(id) => `/photos/albums/${id}`"
            add-title="New Album"
            edit-title="Edit Album"
        >
            <VibeFormGroup label="Album name">
                <VibeFormInput v-model="albumForm.name" placeholder="Summer 2026" />
            </VibeFormGroup>
        </ResourceModal>

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
}
.photo-thumb {
    display: block;
    width: 100%;
    height: 100%;
    padding: 0;
    border: 0;
    border-radius: 6px;
    overflow: hidden;
    background: var(--bs-tertiary-bg);
    cursor: pointer;
}
.photo-thumb:focus-visible {
    outline: 3px solid var(--bs-primary);
    outline-offset: 0;
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
.cell-actions {
    z-index: 20;
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
.cell-star {
    position: absolute;
    top: 2px;
    right: 4px;
    z-index: 2;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.7));
}
.cell-actions {
    position: absolute;
    bottom: 4px;
    right: 4px;
    z-index: 2;
    opacity: 0.55;
    transition: opacity 0.15s;
}
.photo-cell:hover .cell-actions,
.photo-cell:focus-within .cell-actions {
    opacity: 1;
}
/* Touch devices have no hover — keep the trigger fully visible. */
@media (hover: none) {
    .cell-actions {
        opacity: 1;
    }
}
.badge-select {
    position: absolute;
    top: 4px;
    left: 6px;
    color: #fff;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.7));
}

/* Filmstrip */
.filmstrip {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding: 10px 4px 2px;
    justify-content: safe center;
    scroll-padding-inline: 50%;
    height: 92px;
    flex-shrink: 0;
}
.film-thumb {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    opacity: 0.5;
    border: 2px solid transparent;
    flex-shrink: 0;
    transition: opacity 0.15s;
}
.film-thumb:hover {
    opacity: 0.85;
}
.film-thumb.active {
    opacity: 1;
    border-color: var(--bs-primary);
}
</style>
