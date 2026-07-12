<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { triggerDownload } from '../../lib/download';
import { isTextInputTarget } from '../../lib/dom';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { useQuickLook } from '../../composables/useQuickLook';
import QuickLookModal from '../../Components/QuickLookModal.vue';
import PhotoInfoPanel from '../../Components/Photos/PhotoInfoPanel.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import EmptyState from '../../Components/EmptyState.vue';
import FilterChips from '../../Components/FilterChips.vue';
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

const activePane = ref('contents');

// ----- Server filters (album / tag) -----
function applyFilter(patch) {
    const q = { album: props.filters.album, tag: props.filters.tag, ...patch };
    router.get('/photos', Object.fromEntries(Object.entries(q).filter(([, v]) => v)), {
        preserveScroll: true, preserveState: true,
    });
}
const activeAlbum = computed(() => props.albums.find((a) => a.id === props.filters.album));
const activeTag = computed(() => props.tags.find((t) => t.id === props.filters.tag));

// ----- Client filters (camera / starred) -----
const cameraFilter = ref(null);
const filtersOpen = ref(false);
const starredOnly = ref(false);
const cameras = computed(() =>
    [...new Set(props.photos.map((p) => p.camera).filter(Boolean))].sort());
const cameraOptions = computed(() => [
    { value: '', text: 'All cameras' },
    ...cameras.value.map((c) => ({ value: c, text: c })),
]);

// ----- Sort (Picasa-style: taken / added / name) -----
const sortOrder = ref('taken-desc');
const sortOptions = [
    { value: 'taken-desc', text: 'Newest taken', icon: 'sort-down' },
    { value: 'taken-asc', text: 'Oldest taken', icon: 'sort-up' },
    { value: 'added-desc', text: 'Recently added', icon: 'clock-history' },
    { value: 'name-asc', text: 'Name A–Z', icon: 'sort-alpha-down' },
];

const visiblePhotos = computed(() => {
    let list = props.photos;
    if (cameraFilter.value) list = list.filter((p) => p.camera === cameraFilter.value);
    if (starredOnly.value) list = list.filter((p) => isStarred(p));
    const sorted = [...list];
    if (sortOrder.value === 'taken-desc') sorted.sort((a, b) => b.taken_at - a.taken_at);
    else if (sortOrder.value === 'taken-asc') sorted.sort((a, b) => a.taken_at - b.taken_at);
    else if (sortOrder.value === 'added-desc') sorted.sort((a, b) => b.added_at - a.added_at);
    else if (sortOrder.value === 'name-asc') sorted.sort((a, b) => a.name.localeCompare(b.name));
    return sorted;
});

// ----- Timeline grouping (by month; a name sort reads as one flat wall) -----
const groups = computed(() => {
    if (sortOrder.value === 'name-asc') {
        return visiblePhotos.value.length ? [{ key: 'all', label: 'All photos', items: visiblePhotos.value }] : [];
    }
    const out = [];
    const byKey = {};
    for (const p of visiblePhotos.value) {
        if (!byKey[p.date]) {
            byKey[p.date] = { key: p.date, label: p.date_label, items: [] };
            out.push(byKey[p.date]);
        }
        byKey[p.date].items.push(p);
    }
    return out;
});

// ----- Filter chips -----
const activeFilters = computed(() => {
    const out = [];
    if (activeAlbum.value) out.push({ key: 'album', icon: 'collection', label: `Album: ${activeAlbum.value.name}`, clear: () => applyFilter({ album: null }) });
    if (activeTag.value) out.push({ key: 'tag', icon: 'tag-fill', label: `Tag: ${activeTag.value.name}`, clear: () => applyFilter({ tag: null }) });
    if (cameraFilter.value) out.push({ key: 'camera', icon: 'camera', label: cameraFilter.value, clear: () => { cameraFilter.value = null; } });
    if (starredOnly.value) out.push({ key: 'starred', icon: 'star-fill', label: 'Starred', clear: () => { starredOnly.value = false; } });
    return out;
});
function clearAllFilters() {
    cameraFilter.value = null;
    starredOnly.value = false;
    if (props.filters.album || props.filters.tag) applyFilter({ album: null, tag: null });
}

// ----- Breadcrumb -----
const breadcrumbItems = computed(() => [
    { text: 'Photos', album: null, href: '/photos', active: !activeAlbum.value },
    ...(activeAlbum.value ? [{ text: activeAlbum.value.name, album: activeAlbum.value.id, href: '/photos', active: true }] : []),
]);
function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) applyFilter({ album: null });
}

// ----- Thumbnail zoom (Picasa slider): row height of the justified grid -----
function safeLocalStorage(key, fallback = '') {
    try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; }
}
const rowHeight = ref(Number(safeLocalStorage('ph-zoom', '180')) || 180);
watch(rowHeight, (v) => { try { localStorage.setItem('ph-zoom', String(v)); } catch { /* blocked */ } });

// Aspect ratio for the justified layout; falls back to square when the
// metadata extractor hasn't produced dimensions.
function aspect(p) {
    return p.width && p.height ? p.width / p.height : 1;
}

// ----- Selection (hover check, no mode toggle) -----
const selected = ref(new Set());
function toggleSelect(id) {
    selected.value.has(id) ? selected.value.delete(id) : selected.value.add(id);
    selected.value = new Set(selected.value);
}
function clearSelection() {
    selected.value = new Set();
}
async function batchDeleteSelected() {
    if (!await confirm({ title: 'Move to trash', message: `Move ${selected.value.size} photo(s) to trash?`, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    router.post('/files/batch/delete', { ids: [...selected.value] }, {
        preserveScroll: true, onSuccess: clearSelection,
    });
}

// ----- Lightbox -----
const photosRef = computed(() => visiblePhotos.value);
const { quickOpen, quickIndex, quickFile, step, close: qlClose } = useQuickLook(photosRef);

const prevPhoto = computed(() => visiblePhotos.value[quickIndex.value - 1] ?? null);
const nextPhoto = computed(() => visiblePhotos.value[quickIndex.value + 1] ?? null);
const infoOpen = ref(false);
const lightboxMenu = computed(() => {
    if (!quickFile.value) return [];
    return [
        { text: infoOpen.value ? 'Hide info' : 'Info', action: 'info', icon: 'info-circle' },
        { text: isStarred(quickFile.value) ? 'Unstar' : 'Star', action: 'star', icon: isStarred(quickFile.value) ? 'star-fill' : 'star' },
        { text: 'Edit', action: 'edit', icon: 'pencil' },
        { text: 'Delete', action: 'delete', icon: 'trash' },
    ];
});
function onLightboxAction({ item }) {
    const p = quickFile.value;
    if (!p) return;
    if (item.action === 'info') { infoOpen.value = !infoOpen.value; return; }
    if (item.action === 'star') { star(p); return; }
    // Close the lightbox before opening the action's own modal (editor /
    // confirm dialog): two stacked Bootstrap modals share a z-index and the
    // topmost backdrop swallows every click, leaving the editor unusable.
    qlClose();
    if (item.action === 'edit') openEditor(p);
    if (item.action === 'delete') destroyPhoto(p);
}

function openPhoto(p) {
    const idx = visiblePhotos.value.findIndex((x) => x.id === p.id);
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

// Keyboard: lightbox navigation, Escape clears the grid selection.
function onLightboxKey(e) {
    const inTextField = isTextInputTarget(e);
    if (!quickOpen.value) {
        if (!inTextField && e.key === 'Escape') clearSelection();
        return;
    }
    if (inTextField) return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); step(1); }
    else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
    else if (e.key === ' ' || e.code === 'Space') { e.preventDefault(); qlClose(); }
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
let saveSeq = 0;
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
    // Capture the photo this save is for; if the user opens a different
    // photo before this toBlob callback fires, we drop the late result
    // instead of overwriting the new editor's pending save with stale pixels.
    const seq = ++saveSeq;
    const photo = editorPhoto.value;
    canvas.toBlob((blob) => {
        if (!blob || seq !== saveSeq) {
            editorSaving.value = false;
            return;
        }
        const form = new FormData();
        form.append('file', new File([blob], photo.name));
        form.append('note', 'Photo edit');
        form.append('_method', 'put');
        router.post(`/files/${photo.id}/content`, form, {
            forceFormData: true,
            onSuccess: () => { editorOpen.value = false; qlClose(); },
            onFinish: () => { editorSaving.value = false; },
        });
    }, 'image/jpeg', 0.92);
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :folders-visible="false" :detail-visible="false">
        <!-- Breadcrumb + Picasa-style browse controls on one top-bar line:
             zoom slider, starred filter, camera filter, sort; Upload is the
             single solid-primary CTA. -->
        <!-- Title + actions share the top-bar line, matching the house pattern:
             one solid-primary CTA (Upload), quiet light ghosts, filters behind
             the funnel toggle, zoom at the end with real zoom icons. -->
        <template #topBar>
            <div class="d-flex flex-column p-2 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onBreadcrumb">
                        <template #item="{ item, index }">
                            <VibeIcon :icon="index === 0 ? 'images' : 'collection'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                        </template>
                    </VibeBreadcrumb>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                        <VibeButton size="sm" variant="primary" title="Upload photos" aria-label="Upload photos" @click="uploadOpen = true">
                            <VibeIcon icon="upload" />
                        </VibeButton>
                        <VibeButton size="sm" variant="light" title="New album" aria-label="New album" @click="openNewAlbum">
                            <VibeIcon icon="collection" />
                        </VibeButton>
                        <VibeButton
                            size="sm"
                            :variant="starredOnly ? 'primary' : 'light'"
                            title="Starred only"
                            :aria-pressed="starredOnly"
                            aria-label="Show starred only"
                            @click="starredOnly = !starredOnly"
                        >
                            <VibeIcon :icon="starredOnly ? 'star-fill' : 'star'" />
                        </VibeButton>
                        <VibeDropdown size="sm" variant="light" menu-end title="Sort photos" :items="sortOptions" @item-click="sortOrder = $event.item.value">
                            <template #button><VibeIcon icon="sort-down" /></template>
                            <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                        </VibeDropdown>
                        <VibeButton
                            v-if="cameras.length || tags.length"
                            size="sm"
                            variant="light"
                            title="Filter by camera / tag"
                            aria-label="Toggle filters"
                            :class="{ active: filtersOpen || cameraFilter || filters.tag }"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <VibeIcon icon="funnel" />
                        </VibeButton>
                        <div class="d-flex align-items-center gap-1 ms-1" title="Thumbnail size">
                            <VibeIcon icon="zoom-out" class="text-muted" />
                            <VibeSlider v-model="rowHeight" :min="120" :max="320" :step="20" class="ph-zoom" aria-label="Thumbnail size" />
                            <VibeIcon icon="zoom-in" class="text-muted" />
                        </div>
                    </div>
                </div>
                <div v-if="filtersOpen" class="d-flex align-items-center gap-2">
                    <VibeFormSelect
                        v-if="cameras.length"
                        :model-value="cameraFilter || ''"
                        :options="cameraOptions"
                        no-wrapper
                        size="sm"
                        style="max-width: 220px"
                        aria-label="Filter by camera"
                        @update:model-value="cameraFilter = $event || null"
                    />
                    <VibeFormSelect
                        v-if="tags.length"
                        :model-value="filters.tag || ''"
                        :options="[{ value: '', text: 'All tags' }, ...tags.map((t) => ({ value: t.id, text: t.name }))]"
                        no-wrapper
                        size="sm"
                        style="max-width: 180px"
                        aria-label="Filter by tag"
                        @update:model-value="applyFilter({ tag: $event || null })"
                    />
                </div>
            </div>
        </template>

        <template #contents>
            <!-- Selection action bar -->
            <VibeAlert v-if="selected.size" variant="primary" class="d-flex flex-wrap align-items-center gap-2 mx-2 mt-2 mb-0">
                <strong>{{ selected.size }} selected</strong>
                <VibeDropdown variant="primary" size="sm" :items="albums.map((a) => ({ text: a.name, id: a.id }))" @item-click="addSelectedToAlbum($event.item.id)">
                    <template #button><VibeIcon icon="collection" class="me-1" />Add to album</template>
                </VibeDropdown>
                <VibeButton variant="danger" size="sm" outline @click="batchDeleteSelected">
                    <VibeIcon icon="trash" class="me-1" />Delete
                </VibeButton>
                <VibeButton variant="secondary" size="sm" outline @click="clearSelection">Clear</VibeButton>
            </VibeAlert>

            <!-- Single compact chip bar for all active view filters. -->
            <FilterChips :chips="activeFilters" @clear-all="clearAllFilters" />

            <div class="overflow-auto flex-grow-1 px-2 pt-2">
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
                    <span class="text-muted small">{{ activeAlbum.count }} photos</span>
                    <VibeButton variant="danger" size="sm" outline class="ms-2" :aria-label="`Delete album ${activeAlbum.name}`" @click="deleteAlbum(activeAlbum)">
                        <VibeIcon icon="trash" />
                    </VibeButton>
                </div>

                <!-- Timeline -->
                <LoadingSkeleton v-if="loading" :rows="6" :cols="4" />
                <EmptyState v-else-if="!visiblePhotos.length" icon="images" title="No photos here" hint="Upload some, or clear the active filters." />

                <div v-for="g in groups" v-show="!loading" :key="g.key" class="mb-4">
                    <h6 class="text-muted border-bottom pb-1 mb-2">{{ g.label }}</h6>
                    <!-- Justified rows: each cell's flex share is its aspect ratio,
                         so rows fill the full width at ~the chosen height. -->
                    <div class="photo-justified" :style="{ '--rowh': rowHeight + 'px' }">
                        <div
                            v-for="p in g.items"
                            :key="p.id"
                            class="photo-cell"
                            :class="{ selected: selected.has(p.id) }"
                            :style="{ '--ar': aspect(p), flexBasis: `calc(var(--rowh) * ${aspect(p)})`, flexGrow: aspect(p) * 100 }"
                        >
                            <button type="button" class="photo-thumb" :aria-label="`Open ${p.name}`" @click="openPhoto(p)">
                                <img :src="p.thumb_url" :alt="p.name" loading="lazy">
                            </button>

                            <!-- Select (hover check circle) -->
                            <VibeButton
                                variant="link"
                                class="cell-select p-0"
                                :class="{ 'cell-select-idle': !selected.has(p.id) }"
                                :aria-pressed="selected.has(p.id)"
                                :aria-label="(selected.has(p.id) ? 'Deselect ' : 'Select ') + p.name"
                                @click.stop="toggleSelect(p.id)"
                            >
                                <VibeIcon :icon="selected.has(p.id) ? 'check-circle-fill' : 'circle'" :class="selected.has(p.id) ? 'text-primary' : 'text-white'" />
                            </VibeButton>

                            <!-- Star (click to toggle) -->
                            <VibeButton
                                variant="link"
                                class="cell-star p-0"
                                :title="isStarred(p) ? 'Unstar' : 'Star'"
                                :aria-label="(isStarred(p) ? 'Unstar ' : 'Star ') + p.name"
                                @click.stop="star(p)"
                            >
                                <VibeIcon :icon="isStarred(p) ? 'star-fill' : 'star'" :class="isStarred(p) ? 'text-warning' : 'text-body'" />
                            </VibeButton>

                            <!-- Actions -->
                            <div class="cell-actions" @click.stop>
                                <VibeDropdown size="sm" variant="light" menu-end :title="`Photo actions ${p.name}`" :items="photoMenu(p)" @item-click="onPhotoMenu(p, $event)">
                                    <template #button><VibeIcon icon="three-dots-vertical" /><span class="visually-hidden">Photo actions</span></template>
                                    <template #item="{ item: a }"><VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}</template>
                                </VibeDropdown>
                            </div>
                        </div>
                        <!-- Spacer stops the last row from stretching to fill. -->
                        <div class="photo-row-spacer" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </template>
    </ShellLayout>

    <!-- Lightbox -->
    <QuickLookModal
        v-model="quickOpen"
        :file="quickFile"
        :index="quickIndex"
        :total="visiblePhotos.length"
        :prev-file="prevPhoto"
        :next-file="nextPhoto"
        :menu="lightboxMenu"
        @step="step($event)"
        @action="onLightboxAction($event)"
    >
        <template v-if="infoOpen && quickFile" #side>
            <PhotoInfoPanel :photo="quickFile" />
        </template>
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
                        loading="lazy"
                        class="film-thumb"
                        :class="{ active: quickFile && p.id === quickFile.id }"
                        @click="quickIndex = visiblePhotos.findIndex((x) => x.id === p.id)"
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

    <!-- Photo editor (FE-P1-39: chrome pattern A — inline VibeModal,
         same shape as EditorModal but fullscreen with hide-footer for the
         image cropper's own toolbar) -->
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
</template>

<style scoped>
.ph-zoom {
    width: 120px;
}
/* Justified rows (Picasa): flex-grow proportional to aspect ratio makes each
   row fill the width at roughly --rowh tall; the trailing spacer keeps the
   final row left-aligned at natural size. */
.photo-justified {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.photo-cell {
    position: relative;
    height: var(--rowh);
    min-width: calc(var(--rowh) * 0.55);
}
.photo-row-spacer {
    flex-grow: 1000000;
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
.photo-cell.selected {
    outline: 3px solid var(--bs-primary);
    outline-offset: -3px;
}
.cell-select {
    position: absolute;
    top: 2px;
    left: 6px;
    z-index: 2;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.7));
}
.cell-select-idle {
    opacity: 0;
    transition: opacity 0.15s;
}
.photo-cell:hover .cell-select-idle,
.photo-cell:focus-within .cell-select-idle {
    opacity: 1;
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
/* Touch devices have no hover — keep the triggers fully visible. */
@media (hover: none) {
    .cell-actions,
    .cell-select-idle {
        opacity: 1;
    }
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
