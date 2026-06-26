<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesFilename;
use App\Jobs\ProcessUploadedFile;
use App\Models\Album;
use App\Models\File;
use App\Models\Tag;
use App\Services\Audit;
use App\Services\QuotaService;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PhotoController extends Controller
{
    use SanitizesFilename;

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'tiff'];

    // The timeline grid + filters (album / tag).
    public function index(Request $request)
    {
        $userId = auth()->id();

        $query = File::query()
            ->where('owner_id', $userId)
            ->where('is_dir', false)
            ->where('mime', 'like', 'image/%')
            ->with('tags');

        $album = null;
        if ($request->filled('album')) {
            $album = Album::where('owner_id', $userId)->findOrFail($request->integer('album'));
            $query->whereIn('id', $album->photos()->pluck('files.id'));
        }

        if ($request->filled('tag')) {
            $tagId = $request->integer('tag');
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        // Manual order first (drag-to-reorder in the filmstrip), then newest.
        $photos = $query
            ->orderByRaw('sort_order IS NULL') // non-null sort_order first
            ->orderBy('sort_order')
            ->latest('created_at')
            ->limit(2000)->get()
            ->map(fn (File $f) => $this->photoShape($f))->values();

        return Inertia::render('Photos/Index', [
            'photos' => $photos,
            'albums' => $this->albumList($userId),
            'tags' => Tag::where('owner_id', $userId)->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => [
                'album' => $album?->id,
                'tag' => $request->integer('tag') ?: null,
            ],
        ]);
    }

    // Upload images straight into the Photos library (a per-user "Photos" folder).
    public function upload(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|mimes:'.implode(',', self::IMAGE_EXT).'|max:'.Uploads::maxKb(),
        ]);

        $userId = auth()->id();
        $disk = config('filemanager.disk');
        $folder = File::firstOrCreate(
            ['owner_id' => $userId, 'parent_id' => null, 'name' => 'Photos', 'is_dir' => true],
            ['disk' => $disk, 'path' => 'Photos'],
        );

        $incoming = collect($request->file('files', []))->sum(fn ($f) => $f->getSize());
        if (app(QuotaService::class)->wouldExceed($userId, $incoming)) {
            throw ValidationException::withMessages(['files' => 'This upload would exceed your storage quota.']);
        }

        foreach ($request->file('files', []) as $upload) {
            $path = $upload->store("uploads/{$userId}", $disk);
            $file = File::create([
                'name' => $this->sanitizeFilename($upload->getClientOriginalName()),
                'path' => $path,
                'disk' => $disk,
                'is_dir' => false,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'hash' => hash_file('sha256', $upload->getRealPath()),
                'status' => File::STATUS_PENDING,
                'parent_id' => $folder->id,
                'owner_id' => $userId,
            ]);
            ProcessUploadedFile::dispatch($file->id);
            Audit::log('photo.upload', $file, ['size' => $file->size]);
        }

        return back()->with('success', 'Photos uploaded.');
    }

    public function storeAlbum(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        Album::create(['owner_id' => auth()->id(), 'name' => $data['name']]);

        return back()->with('success', 'Album created.');
    }

    public function destroyAlbum(Album $album)
    {
        $this->authorize('update', $album);
        $album->delete();

        return back()->with('success', 'Album deleted.');
    }

    public function addToAlbum(Request $request, Album $album)
    {
        $this->authorize('update', $album);
        $ids = $this->ownedPhotoIds($request);
        $album->photos()->syncWithoutDetaching($ids);
        if (! $album->cover_file_id && $ids) {
            $album->update(['cover_file_id' => $ids[0]]);
        }

        return back()->with('success', 'Added to album.');
    }

    public function removeFromAlbum(Request $request, Album $album)
    {
        $this->authorize('update', $album);
        $album->photos()->detach($this->ownedPhotoIds($request));

        return back()->with('success', 'Removed from album.');
    }

    public function setCover(Request $request, Album $album)
    {
        $this->authorize('update', $album);
        $id = $this->ownedPhotoIds($request)[0] ?? null;
        abort_unless($id, 404, 'Photo not found.');
        $album->update(['cover_file_id' => $id]);

        return back()->with('success', 'Cover updated.');
    }

    // Persist a manual photo order (drag-to-reorder filmstrip). Only the user's
    // own image files are touched; sort_order follows the given id sequence.
    public function reorder(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $ownedImageIds = File::where('owner_id', auth()->id())
            ->where('is_dir', false)
            ->where('mime', 'like', 'image/%')
            ->pluck('id')
            ->flip();

        $position = 0;
        foreach ($request->input('ids') as $id) {
            if ($ownedImageIds->has((int) $id)) {
                File::whereKey($id)->update(['sort_order' => $position++]);
            }
        }

        return back()->with('success', 'Order updated.');
    }

    /** Validate + return file ids that are image files owned by the user. */
    private function ownedPhotoIds(Request $request): array
    {
        $request->validate(['file_ids' => 'required|array', 'file_ids.*' => 'integer']);

        return File::whereIn('id', $request->input('file_ids'))
            ->where('owner_id', auth()->id())
            ->where('mime', 'like', 'image/%')
            ->pluck('id')->all();
    }

    private function albumList(int $userId): \Illuminate\Support\Collection
    {
        return Album::where('owner_id', $userId)
            ->withCount('photos')
            ->with('cover')
            ->latest('id')
            ->get()
            ->map(fn (Album $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'count' => $a->photos_count,
                'cover_url' => $a->cover && $a->cover->thumbnail_path
                    ? route('files.thumbnail', $a->cover)
                    : null,
            ]);
    }

    private function photoShape(File $f): array
    {
        $meta = $f->metadata ?? [];
        $taken = $meta['taken_at'] ?? $meta['date_taken'] ?? $meta['DateTimeOriginal'] ?? null;
        $ts = ($taken ? @strtotime($taken) : false) ?: $f->created_at->getTimestamp();

        return [
            'id' => $f->id,
            'name' => $f->name,
            'url' => route('files.raw', $f),
            'thumb_url' => $f->thumbnail_path ? route('files.thumbnail', $f) : route('files.raw', $f),
            'starred' => (bool) $f->starred,
            'status' => $f->status,
            'tags' => $f->relationLoaded('tags')
                ? $f->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
            'taken_at' => $ts,
            'date' => date('Y-m', $ts),
            'date_label' => date('F Y', $ts),
        ];
    }
}
