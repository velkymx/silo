<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesFilesystem;
use App\Http\Controllers\Concerns\SanitizesFilename;
use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\RssItem;
use App\Models\Tag;
use App\Services\Audit;
use App\Services\QuotaService;
use App\Support\FileResponse;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FileController extends Controller
{
    use ManagesFilesystem, SanitizesFilename;
    // Display files and folders for the current (DB-backed) folder.
    public function index(Request $request, \App\Services\FileSearch $search, \App\Services\SectionListing $sectionListing)
    {
        $userId = auth()->id();

        // Unified shell section rail: all | recent | starred | shared | trash.
        // The section can arrive either as a query param or as a route default
        // (e.g. /shared, /trash retain their own URLs but render this shell).
        $raw = $request->get('section', $request->route('section'));
        $section = in_array($raw, ['all', 'recent', 'starred', 'shared', 'trash'], true) ? $raw : 'all';

        $current = null;
        if ($request->filled('folder')) {
            $current = File::folders()->where('owner_id', $userId)->findOrFail($request->integer('folder'));
            $this->authorize('view', $current);
        }

        $sort = in_array($request->get('sort'), ['name', 'size', 'created_at'], true)
            ? $request->get('sort')
            : 'name';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        // A search runs when there's free text or any structured filter; a bare
        // tag (with nothing else) is a Smart Folder, handled separately below.
        $advanced = $search->isAdvanced($request);
        $useSearch = $request->filled('search') || $advanced;
        $scopeFolderId = $search->scopeFolderId($request);
        $starredOnly = $request->boolean('starred') || $section === 'starred';
        $recentOnly = $request->boolean('recent') || $section === 'recent';
        $activeTag = $request->filled('tag')
            ? Tag::where('owner_id', $userId)->find($request->integer('tag'))
            : null;

        // ME-03: keep the immediate page load small. We send at most 200
        // folders; the move/copy picker and tree-lazy-load fetch more via
        // GET /folders (parent + q filters, 200 cap per call).
        $allFolders = File::folders()->where('owner_id', $userId)
            ->orderBy('name')->limit(201)->get(['id', 'name', 'parent_id']);
        $allFoldersCapped = $allFolders->count() > 200;
        if ($allFoldersCapped) $allFolders = $allFolders->take(200);
        $folderById = $allFolders->keyBy('id');

        // Starred RSS items are only populated on the starred surface; the
        // other branches leave it as an empty collection so the prop is
        // always defined for the page.
        $rssItems = collect();

        // The VibeUI DataTable paginates client-side; a safety cap keeps the
        // payload bounded on very large result sets.
        $cap = 1000;

        if ($section === 'shared') {
            // Items shared with me (any owner) — permission-gated by the service.
            $shared = $sectionListing->shared(auth()->user());
            $folders = $shared->where('is_dir', true)->values();
            $files = $shared->where('is_dir', false)->values();
        } elseif ($section === 'trash') {
            // My trashed deletion-roots; restore/purge go through the trash routes.
            $trashed = $sectionListing->trashed($userId);
            $folders = $trashed->where('is_dir', true)->values();
            $files = $trashed->where('is_dir', false)->values();
        } elseif ($useSearch) {
            // Unified search: free text + date/size/type/tag/scope in one query.
            $folders = collect();
            $files = $search->build($request, $userId, $allFolders)
                ->with(['versions', 'tags'])->latest('created_at')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } elseif ($activeTag) {
            // Tag filter spans every folder the user owns (folders + files).
            $folders = $activeTag->files()->where('owner_id', $userId)->where('is_dir', true)
                ->withCount('children')->with('tags')->orderBy('name')->get()
                ->map(fn (File $folder) => $this->folderShape($folder));
            $files = $activeTag->files()->where('owner_id', $userId)->where('is_dir', false)
                ->with(['versions', 'tags'])->orderBy('name')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } elseif ($starredOnly) {
            // Starred items across every folder the user owns.
            $folders = File::query()->where('owner_id', $userId)->where('starred', true)->folders()
                ->withCount('children')->with('tags')->orderBy('name')->get()
                ->map(fn (File $folder) => $this->folderShape($folder));
            $files = File::query()->where('owner_id', $userId)->where('starred', true)->files()
                ->with(['versions', 'tags'])->orderBy('name')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
            // The /starred view is the user's "everything I care about" surface,
            // so it has to span every content type. RSS items starred in the
            // RSS reader show up here alongside starred files/folders, with the
            // newest star at the top.
            $rssItems = RssItem::ownedBy($userId)
                ->whereHas('feed', fn ($q) => $q->where('user_id', $userId))
                ->starred()
                ->with('feed:id,title,folder')
                ->orderByDesc('starred_at')
                ->limit($cap)
                ->get()
                ->map(fn (RssItem $i) => [
                    'id' => $i->id,
                    'feed_id' => $i->feed_id,
                    'feed_title' => $i->feed?->title,
                    'feed_folder' => $i->feed?->folder,
                    'title' => $i->title,
                    'excerpt' => $i->excerpt,
                    'author' => $i->author,
                    'categories' => $i->categories ?? [],
                    'image_url' => $i->image_url,
                    'url' => $i->url,
                    'published_at' => optional($i->published_at)->toIso8601String(),
                    'is_read' => (bool) $i->is_read,
                    'is_starred' => (bool) $i->is_starred,
                ])
                ->values();
        } elseif ($recentOnly) {
            // Most recently uploaded files across every folder.
            $folders = collect();
            $files = File::query()->where('owner_id', $userId)->files()
                ->with(['versions', 'tags'])->latest('created_at')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } else {
            $query = File::query()->where('owner_id', $userId)->where('parent_id', $current?->id);

            $folders = (clone $query)->folders()->withCount('children')->with('tags')
                ->orderBy($sort, $direction)->get()
                ->map(fn (File $folder) => $this->folderShape($folder));

            $files = (clone $query)->files()->with(['versions', 'tags'])
                ->orderBy($sort, $direction)->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file));
        }

        return Inertia::render('Files/Index', [
            'folders' => $folders->values(),
            'files' => $files->values(),
            'rssItems' => $rssItems ?? collect(),
            'current' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'breadcrumbs' => $this->breadcrumbs($current),
            'searching' => $useSearch,
            'advanced' => $advanced,
            'section' => $section,
            'starredOnly' => $starredOnly,
            'recentOnly' => $recentOnly,
            'flat' => $useSearch || (bool) $activeTag || $starredOnly || $recentOnly
                || $section === 'shared' || $section === 'trash',
            'activeTag' => $activeTag ? ['id' => $activeTag->id, 'name' => $activeTag->name] : null,
            'allFolders' => $allFolders,
            'allFoldersCapped' => $allFoldersCapped,
            'allTags' => Tag::where('owner_id', $userId)->orderBy('name')->get(['id', 'name', 'color']),
            // 'storage' is shared globally by HandleInertiaRequests — not duplicated here.
            'maxUploadKb' => Uploads::maxKb(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
                'date_target' => $request->string('date_target')->toString() === 'edited' ? 'edited' : 'uploaded',
                'date_from' => $request->string('date_from')->toString() ?: null,
                'date_to' => $request->string('date_to')->toString() ?: null,
                'size_min' => $request->input('size_min'),
                'size_max' => $request->input('size_max'),
                'ftype' => $request->string('ftype')->toString() ?: null,
                'tag' => $activeTag?->id,
                'scope' => $scopeFolderId ? 'folder' : 'all',
            ],
        ]);
    }

    // Create a new text/markdown file from editor content.
    public function createText(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string', // a text file must have content
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $disk = config('filemanager.disk');

        $name = trim((string) $request->string('name'));
        if (! pathinfo($name, PATHINFO_EXTENSION)) {
            $name .= '.md';
        }
        $content = (string) $request->input('content');

        // Serialize quota-affecting writes per user (same lock as upload) so a
        // concurrent create/upload can't both pass the quota check.
        return Cache::lock("user-quota-{$userId}", 30)->block(10, function () use ($userId, $parent, $disk, $name, $content) {
            if (app(QuotaService::class)->wouldExceed($userId, strlen($content))) {
                throw ValidationException::withMessages(['name' => 'This would exceed your storage quota.']);
            }

            $path = "uploads/{$userId}/".Str::random(40);
            if ($ext = pathinfo($name, PATHINFO_EXTENSION)) {
                $path .= ".{$ext}";
            }
            Storage::disk($disk)->put($path, $content);

            $file = $this->withFolderLock($userId, $parent?->id, function () use ($name, $path, $disk, $content, $parent, $userId) {
                $this->assertNoCollision($parent?->id, $name, $userId);

                return File::create([
                    'name' => $name,
                    'path' => $path,
                    'disk' => $disk,
                    'is_dir' => false,
                    'mime' => str_ends_with($name, '.md') ? 'text/markdown' : 'text/plain',
                    'size' => strlen($content),
                    'hash' => hash('sha256', $content),
                    'status' => File::STATUS_PENDING,
                    'parent_id' => $parent?->id,
                    'owner_id' => $userId,
                ]);
            });
            app(QuotaService::class)->invalidate($userId);

            ProcessUploadedFile::dispatch($file->id);
            Audit::log('file.create', $file);

            return redirect()->route('files.index', ['folder' => $parent?->id])
                ->with('success', "Created “{$name}”.");
        });
    }

    // Office document types that can be created blank and edited in the browser.
    private const NEW_DOC_TYPES = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Open the editor on a blank document of the given type (the file is not
    // created until the first Save).
    public function newDocument(Request $request, string $type)
    {
        abort_unless(isset(self::NEW_DOC_TYPES[$type]), 404);

        return Inertia::render('Files/Editor', [
            'file' => null,
            'create' => [
                'type' => $type,
                'name' => 'Untitled.'.$type,
                'parent_id' => $request->integer('folder') ?: null,
            ],
        ]);
    }

    // Persist a freshly created office document (binary blob from the editor).
    public function storeDocument(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', array_keys(self::NEW_DOC_TYPES)),
            'parent_id' => 'nullable|integer|exists:files,id',
            'file' => 'required|file|max:'.Uploads::maxKb(),
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $disk = config('filemanager.disk');
        $type = $request->input('type');

        $name = trim((string) $request->string('name'));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $type) {
            $name .= '.'.$type;
        }
        $bytes = $request->file('file')->get();

        return Cache::lock("user-quota-{$userId}", 30)->block(10, function () use ($userId, $parent, $disk, $type, $name, $bytes) {
            if (app(QuotaService::class)->wouldExceed($userId, strlen($bytes))) {
                throw ValidationException::withMessages(['name' => 'This would exceed your storage quota.']);
            }

            $path = "uploads/{$userId}/".Str::random(40).'.'.$type;
            Storage::disk($disk)->put($path, $bytes);

            $file = $this->withFolderLock($userId, $parent?->id, function () use ($name, $path, $disk, $type, $bytes, $parent, $userId) {
                $this->assertNoCollision($parent?->id, $name, $userId);

                return File::create([
                    'name' => $name,
                    'path' => $path,
                    'disk' => $disk,
                    'is_dir' => false,
                    'mime' => self::NEW_DOC_TYPES[$type],
                    'size' => strlen($bytes),
                    'hash' => hash('sha256', $bytes),
                    'status' => File::STATUS_PENDING,
                    'parent_id' => $parent?->id,
                    'owner_id' => $userId,
                ]);
            });
            app(QuotaService::class)->invalidate($userId);

            ProcessUploadedFile::dispatch($file->id);
            Audit::log('file.create', $file);

            return redirect()->route('files.index', ['folder' => $parent?->id])
                ->with('success', "Created “{$name}”.");
        });
    }

    // Open the full-screen editor page for an editable file (office docs, text).
    public function edit(File $file)
    {
        $this->authorize('update', $file);
        abort_if($file->is_dir, 404);

        return Inertia::render('Files/Editor', [
            'file' => [
                'id' => $file->id,
                'name' => $file->name,
                'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
                'mime' => $file->mime,
                'version' => $file->version,
                'parent_id' => $file->parent_id,
                'raw_url' => route('files.raw', $file),
            ],
        ]);
    }

    // Save edited content as a new version of a file. Accepts either a binary
    // upload ("file", for office docs) or raw text ("content"), plus an optional
    // git-style note describing what changed.
    public function updateContent(Request $request, File $file)
    {
        $this->authorize('update', $file);
        abort_if($file->is_dir, 404);

        $request->validate([
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:'.Uploads::maxKb(),
            'note' => 'nullable|string|max:1000',
        ]);

        $upload = $request->file('file');
        if (! $upload && ! $request->has('content')) {
            throw ValidationException::withMessages(['content' => 'Nothing to save.']);
        }

        $content = $upload ? $upload->get() : (string) $request->input('content');
        $note = $request->filled('note') ? trim((string) $request->input('note')) : null;
        $userId = $file->owner_id;
        $disk = config('filemanager.disk');

        return Cache::lock("user-quota-{$userId}", 30)->block(10, function () use ($file, $userId, $disk, $content, $note) {
            if (app(QuotaService::class)->wouldExceed($userId, strlen($content) - (int) $file->size)) {
                throw ValidationException::withMessages(['file' => 'This would exceed your storage quota.']);
            }

            $newPath = "uploads/{$userId}/".Str::random(40);
            if ($ext = pathinfo($file->name, PATHINFO_EXTENSION)) {
                $newPath .= ".{$ext}";
            }
            Storage::disk($disk)->put($newPath, $content);

            DB::transaction(function () use ($file, $newPath, $disk, $content, $note) {
                $this->snapshotVersion($file, null, $note);
                $file->update([
                    'path' => $newPath,
                    'disk' => $disk,
                    'size' => strlen($content),
                    'hash' => hash('sha256', $content),
                    'version' => $file->version + 1,
                    'status' => File::STATUS_PENDING,
                    'referenced' => false, // edited content is now app-owned
                    'content_edited_at' => now(),
                ]);
            });
            app(QuotaService::class)->invalidate($userId);

            ProcessUploadedFile::dispatch($file->id);
            Audit::log('file.edit', $file);

            return redirect()->route('files.index', ['folder' => $file->parent_id])
                ->with('success', 'Saved.');
        });
    }

    // Toggle a file's or folder's starred (favorite) flag.
    public function star(File $file)
    {
        $this->authorize('update', $file);
        $file->update(['starred' => ! $file->starred]);

        return back()->with('success', $file->starred ? 'Added to starred.' : 'Removed from starred.');
    }

    // Replace a file's tags from a list of names (creating tags as needed).
    public function syncTags(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate([
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ]);

        $ids = collect($request->input('tags', []))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(
                ['owner_id' => $file->owner_id, 'name' => $name],
            )->id);

        $file->tags()->sync($ids);

        return back()->with('success', 'Tags updated.');
    }

    // Human-readable folder path for a search result ("Home / Docs / 2026"),
    // resolved from a prefetched folder map to avoid per-file queries.
    protected function locationLabel(File $file, \Illuminate\Support\Collection $folderById): array
    {
        $names = [];
        for ($id = $file->parent_id; $id && ($node = $folderById->get($id)); $id = $node->parent_id) {
            array_unshift($names, $node->name);
        }

        return [
            'folder_id' => $file->parent_id,
            'path' => 'Home'.collect($names)->reduce(fn ($carry, $n) => $carry.' / '.$n, ''),
        ];
    }

    // Shape a folder model for the frontend (id, counts, tags).
    protected function folderShape(File $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'is_dir' => true,
            'starred' => (bool) $folder->starred,
            'item_count' => $folder->children_count ?? 0,
            'updated_at' => $folder->updated_at->format('Y-m-d H:i'),
            'tags' => $folder->relationLoaded('tags')
                ? $folder->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
        ];
    }

    // Shape a file model for the frontend.
    protected function transform(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'is_dir' => false,
            'size' => $file->size,
            'mime' => $file->mime,
            'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
            'url' => route('files.raw', $file),
            'status' => $file->status,
            'metadata' => $file->metadata,
            'thumb_url' => $file->thumbnail_path ? route('files.thumbnail', $file) : null,
            'tags' => $file->relationLoaded('tags')
                ? $file->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
            'hash' => $file->hash,
            'version' => $file->version,
            'starred' => (bool) $file->starred,
            'versions' => $file->relationLoaded('versions')
                ? $file->versions->map(fn (FileVersion $v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'note' => $v->note,
                    'size' => $v->size,
                    'created_at' => $v->created_at->format('Y-m-d H:i'),
                ])->values()
                : [],
            'created_at' => $file->created_at->format('Y-m-d H:i'),
        ];
    }

    // Build the breadcrumb trail from root to the current folder.
    protected function breadcrumbs(?File $current): array
    {
        $trail = [];
        for ($node = $current; $node; $node = $node->parent) {
            array_unshift($trail, ['id' => $node->id, 'name' => $node->name]);
        }

        return $trail;
    }

    // Upload one or more files into the current folder.
    public function upload(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|max:'.Uploads::maxKb(),
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $disk = config('filemanager.disk');

        // Serialize quota-affecting writes per user so concurrent uploads can't
        // each pass the check and collectively blow the quota (TOCTOU race).
        return Cache::lock("user-quota-{$userId}", 30)->block(10, function () use ($request, $userId, $parent, $disk) {
            // Reject the whole batch if it would push the user over their quota.
            $incoming = collect($request->file('files', []))->sum(fn ($f) => $f->getSize());
            if (app(QuotaService::class)->wouldExceed($userId, $incoming)) {
                throw ValidationException::withMessages([
                    'files' => 'This upload would exceed your storage quota.',
                ]);
            }

            foreach ($request->file('files', []) as $upload) {
                // Storage is flat per user; the folder hierarchy lives entirely in the DB.
                $path = $upload->store("uploads/{$userId}", $disk);
                if ($path === false) {
                    throw ValidationException::withMessages(['files' => 'One or more files could not be saved. Please try again.']);
                }

                $cleanName = $this->sanitizeFilename($upload->getClientOriginalName());

                $attributes = [
                    'name' => $cleanName,
                    'path' => $path,
                    'disk' => $disk,
                    'mime' => $upload->getClientMimeType(),
                    'size' => $upload->getSize(),
                    'hash' => hash_file('sha256', $upload->getRealPath()),
                    'status' => File::STATUS_PENDING,
                ];

                // An upload onto an existing file name becomes a new version of that
                // file. (resolveFolder() guarantees $parent is owned by the uploader,
                // so a cross-owner collision in a foreign folder can't occur here.)
                $existing = File::files()
                    ->where('owner_id', $userId)
                    ->where('parent_id', $parent?->id)
                    ->where('name', $cleanName)
                    ->latest('id')->first();

                $file = $existing
                    ? $this->overwrite($existing, $attributes, $userId)
                    : File::create($attributes + ['is_dir' => false, 'parent_id' => $parent?->id, 'owner_id' => $userId]);

                // Refine mime + extract metadata off the request cycle.
                ProcessUploadedFile::dispatch($file->id);
                Audit::log('file.upload', $file, ['size' => $file->size]);
            }

            app(QuotaService::class)->invalidate($userId);

            return redirect()->route('files.index', ['folder' => $parent?->id])
                ->with('success', 'Files uploaded successfully!');
        });
    }

    // Download a file resolved by DB id (no client-supplied paths).
    public function download(File $file)
    {
        $this->authorize('view', $file);

        abort_if($file->is_dir, 404);
        // Fail closed: only serve files that finished processing/AV-scanning.
        // Referenced (admin-imported, trusted) blobs are exempt — they were
        // never an untrusted user upload.
        abort_unless($file->status === File::STATUS_READY || $file->referenced, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        Audit::log('file.download', $file);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    // Stream a file's bytes inline (policy-gated) for previews/embeds.
    public function raw(File $file)
    {
        $this->authorize('view', $file);

        abort_if($file->is_dir, 404);
        // Fail closed: only serve files that finished processing/AV-scanning.
        // Referenced (admin-imported, trusted) blobs are exempt — they were
        // never an untrusted user upload.
        abort_unless($file->status === File::STATUS_READY || $file->referenced, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return FileResponse::serve(Storage::disk($file->disk), $file->path, $file->name, $file->mime);
    }

    // Stream a file's cached thumbnail (policy-gated, not a public URL).
    public function thumbnail(File $file)
    {
        $this->authorize('view', $file);

        $disk = \App\Services\ThumbnailGenerator::disk();
        abort_unless(
            $file->thumbnail_path && Storage::disk($disk)->exists($file->thumbnail_path),
            404
        );

        // Thumbnails are app-generated JPEGs — safe to render inline.
        return FileResponse::serve(Storage::disk($disk), $file->thumbnail_path, 'thumbnail.jpg', 'image/jpeg');
    }

    // Soft-delete a file or folder (and its contents) into the trash.
    // Blobs are kept until the item is purged from the trash, so it can be restored.
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        DB::transaction(function () use ($file) {
            if ($file->is_dir) {
                $this->trashSubtree($file);
            }

            $file->delete();
        });

        Audit::log('file.trash', $file);
        app(QuotaService::class)->invalidate(auth()->id());

        return redirect()->route('files.index', ['folder' => $file->parent_id])
            ->with('success', 'Moved to trash.');
    }

    // Rename a file or folder (display name only; storage path is stable).
    public function rename(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate(['name' => 'required|string|max:255']);
        $name = trim((string) $request->string('name'));

        $this->withFolderLock($file->owner_id, $file->parent_id, function () use ($file, $name) {
            $this->assertNoCollision($file->parent_id, $name, $file->owner_id, $file->id);
            $file->update(['name' => $name]);
        });

        return back()->with('success', 'Renamed successfully!');
    }

    // Move a file or folder into another folder (DB reparent only).
    public function move(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate(['target_id' => 'nullable|integer|exists:files,id']);
        // The destination is resolved + authorized against the ACTOR, never the
        // file's owner — otherwise source access would imply write to the
        // owner's tree.
        $target = $this->resolveFolder($request->input('target_id'), auth()->id());

        if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot move a folder into itself or one of its subfolders.',
            ]);
        }

        $this->withFolderLock($file->owner_id, $target?->id, function () use ($file, $target) {
            $this->assertNoCollision($target?->id, $file->name, $file->owner_id, $file->id);
            $file->update(['parent_id' => $target?->id]);
        });

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Moved successfully!');
    }

    // Copy a file or folder (deep) into another folder.
    public function copy(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $request->validate(['target_id' => 'nullable|integer|exists:files,id']);
        // Resolve + authorize the destination against the ACTOR, and the copy is
        // owned by the actor — a viewer can save a copy into their own space but
        // never write into the owner's tree.
        $actorId = (int) auth()->id();
        $target = $this->resolveFolder($request->input('target_id'), $actorId);

        if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot copy a folder into itself or one of its subfolders.',
            ]);
        }

        // A copy duplicates every blob in the subtree onto the actor's quota.
        if (app(QuotaService::class)->wouldExceed($actorId, $this->subtreeSize($file))) {
            throw ValidationException::withMessages([
                'target_id' => 'This copy would exceed your storage quota.',
            ]);
        }

        $this->withFolderLock($actorId, $target?->id, function () use ($file, $target, $actorId) {
            $name = $this->uniqueName($target?->id, $file->name, $actorId);
            DB::transaction(fn () => $this->copyNode($file, $target?->id, $name, $actorId));
        });
        app(QuotaService::class)->invalidate($actorId);

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Copied successfully!');
    }

    // Total bytes of the file blobs in a subtree (folders hold no bytes),
    // computed in one recursive CTE so a deep copy's quota check isn't N+1.
    protected function subtreeSize(File $file): int
    {
        if (! $file->is_dir) {
            return (int) $file->size;
        }

        $row = DB::selectOne('
            WITH RECURSIVE sub(id) AS (
                SELECT id FROM files WHERE id = :root
                UNION ALL
                SELECT f.id FROM files f INNER JOIN sub ON f.parent_id = sub.id
                    WHERE f.deleted_at IS NULL
            )
            SELECT COALESCE(SUM(f.size), 0) AS total
            FROM files f INNER JOIN sub ON f.id = sub.id
            WHERE f.is_dir = 0 AND f.deleted_at IS NULL
        ', ['root' => $file->id]);

        return (int) ($row->total ?? 0);
    }

    // Replace a file's content, archiving its current blob as a version.
    protected function overwrite(File $file, array $attributes, int $userId): File
    {
        DB::transaction(function () use ($file, $attributes, $userId) {
            $this->snapshotVersion($file, $userId);

            // The new blob is app-managed, so the file is no longer a referenced
            // import — otherwise trash:purge would skip deleting the new bytes.
            $file->update($attributes + ['version' => $file->version + 1, 'referenced' => false, 'content_edited_at' => now()]);
        });

        return $file;
    }

    // Archive the file's current blob as a historical version row.
    protected function snapshotVersion(File $file, ?int $createdBy = null, ?string $note = null): void
    {
        app(\App\Services\FileVersioning::class)->snapshot($file, $createdBy, $note);
    }

    // Recursively copy a node under a new parent, duplicating file blobs.
    protected function copyNode(File $source, ?int $parentId, string $name, int $ownerId): File
    {
        $disk = config('filemanager.disk');
        $path = $source->name; // folder placeholder

        if (! $source->is_dir) {
            $path = "uploads/{$ownerId}/".Str::random(40);
            if ($extension = pathinfo($source->path, PATHINFO_EXTENSION)) {
                $path .= ".{$extension}";
            }
            Storage::disk($source->disk)->copy($source->path, $path);
        }

        $copy = File::create([
            'name' => $name,
            'path' => $path,
            'disk' => $source->is_dir ? $disk : $source->disk,
            'is_dir' => $source->is_dir,
            'mime' => $source->mime,
            'size' => $source->size,
            'hash' => $source->hash,
            // File copies re-process so they get their own thumbnail/metadata
            // and never inherit a stuck PENDING/FAILED state from the source.
            'status' => $source->is_dir ? $source->status : File::STATUS_PENDING,
            'metadata' => $source->metadata,
            'thumbnail_path' => null,
            'parent_id' => $parentId,
            'owner_id' => $ownerId,
        ]);

        if ($source->is_dir) {
            foreach ($source->children as $child) {
                $this->copyNode($child, $copy->id, $child->name, $ownerId);
            }
        } else {
            ProcessUploadedFile::dispatch($copy->id)->afterCommit();
        }

        return $copy;
    }

    /**
     * Generate a non-colliding name for a copy of `$name` in `$parentId`. TOCTOU
     * is prevented by requiring the caller to hold `withFolderLock($ownerId,
     * $parentId)` — two concurrent copies serialize on the lock so the second
     * sees the first's row before computing its suffix.
     */
    protected function uniqueName(?int $parentId, string $name, int $ownerId): string
    {
        $base = $name;
        $extension = '';
        if (($dot = strrpos($name, '.')) !== false && $dot > 0) {
            $base = substr($name, 0, $dot);
            $extension = substr($name, $dot);
        }

        $candidate = $name;
        $n = 0;
        while (File::where('owner_id', $ownerId)->where('parent_id', $parentId)->where('name', $candidate)->exists()) {
            $n++;
            $suffix = $n === 1 ? ' (copy)' : " (copy {$n})";
            $candidate = "{$base}{$suffix}{$extension}";
        }

        return $candidate;
    }
}
